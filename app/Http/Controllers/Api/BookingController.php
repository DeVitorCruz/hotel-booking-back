<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\Booking\BookingPaymentService;
use App\Services\Booking\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guest-facing booking endpoints. No account required: the guest proves
 * ownership with the manage token (X-Booking-Token header or ?token=),
 * returned on creation and included in the confirmation email link.
 */
class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookings,
        private BookingPaymentService $payments,
    ) {}

    /**
     * POST /bookings — hold a room and start payment.
     * PayPal: response.payment.paypal_order_id feeds the JS SDK createOrder callback.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->hold($request->validated(), auth('sanctum')->user());

        try {
            $this->payments->start($booking, $request->only('return_url', 'cancel_url'));
        } catch (BookingException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // Gateway down: release the room immediately instead of waiting for the hold to lapse.
            if ($booking->isPending()) {
                $this->bookings->cancel($booking, 'Payment could not be started.', notifyGuest: false);
            }
            report($e);

            throw new BookingException('The payment provider is unavailable. Please try again shortly.', 'gateway_unavailable', 503);
        }

        return (new BookingResource($this->load($booking)))->withToken()
            ->response()->setStatusCode(201);
    }

    /** GET /bookings/{reference} */
    public function show(Request $request, string $reference): BookingResource
    {
        return new BookingResource($this->load($this->authorized($request, $reference)));
    }

    /**
     * POST /bookings/{reference}/paypal/capture  { "order_id": "..." }
     * Call from the JS SDK onApprove callback.
     */
    public function capture(Request $request, string $reference): BookingResource
    {
        $data = $request->validate(['order_id' => ['required', 'string', 'max:64']]);
        $booking = $this->authorized($request, $reference);

        try {
            $booking = $this->payments->capture($booking, $data['order_id']);
        } catch (\RuntimeException $e) {
            if ($e instanceof BookingException) {
                throw $e;
            }
            report($e);

            throw new BookingException('Could not reach PayPal to complete the payment. You have not been charged twice; please retry.', 'gateway_unavailable', 503);
        }

        return new BookingResource($this->load($booking));
    }

    /**
     * POST /bookings/{reference}/paypal/retry — new PayPal order for a booking still on hold.
     */
    public function retry(Request $request, string $reference): BookingResource
    {
        $booking = $this->authorized($request, $reference);
        $this->payments->retry($booking, $request->only('return_url', 'cancel_url'));

        return new BookingResource($this->load($booking));
    }

    /**
     * POST /bookings/{reference}/cancel
     * Free cancellation window → automatic PayPal refund (if enabled).
     */
    public function cancel(Request $request, string $reference): JsonResponse
    {
        $booking = $this->authorized($request, $reference);

        if ($booking->check_in->isPast() && ! $booking->check_in->isToday()) {
            throw BookingException::invalidState('Past bookings cannot be cancelled online.');
        }

        $free = $this->bookings->isFreeCancellation($booking);
        [$booking, $refunded] = $this->payments->cancelAndRefund(
            $booking,
            'Cancelled by guest.',
            refund: $free && app('hotel.settings')->get('auto_refund_on_cancel', true),
        );

        return response()->json([
            'booking' => new BookingResource($this->load($booking)),
            'free_cancellation' => $free,
            'refunded_amount' => $refunded,
        ]);
    }

    /** GET /my/bookings — bookings of the logged-in guest (by account or email). */
    public function mine(Request $request)
    {
        $user = $request->user();

        return BookingResource::collection(
            Booking::query()
                ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('guest_email', mb_strtolower($user->email)))
                ->with(['roomType.images', 'payments'])
                ->latest('check_in')
                ->paginate(20)
        );
    }

    // --- helpers --------------------------------------------------------

    private function authorized(Request $request, string $reference): Booking
    {
        $booking = Booking::query()->where('reference', strtoupper($reference))->first();
        $token = $request->header('X-Booking-Token') ?? $request->query('token');
        $user = auth('sanctum')->user();

        $allowed = $booking && (
            $booking->matchesManageToken($token)
            || ($user && ($booking->user_id === $user->id || $user->isStaff()))
        );

        // Same 404 for "missing" and "wrong token" so references can't be probed.
        abort_unless($allowed, 404, 'Booking not found.');

        return $booking;
    }

    private function load(Booking $booking): Booking
    {
        return $booking->refresh()->load(['roomType.images', 'roomType.amenities', 'payments']);
    }
}
