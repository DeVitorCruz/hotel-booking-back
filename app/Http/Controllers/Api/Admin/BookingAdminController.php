<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\Booking\BookingPaymentService;
use App\Services\Booking\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Front-desk operations: list, create manual bookings, check-in/out,
 * cancel (with optional refund), record pay-at-hotel payments.
 */
class BookingAdminController extends Controller
{
    public function __construct(
        private BookingService $bookings,
        private BookingPaymentService $payments,
    ) {}

    /** GET /admin/bookings?status=&from=&to=&q= */
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'string'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Booking::query()->with(['roomType', 'room', 'payments', 'statusHistory']);

        if ($request->filled('status')) {
            $query->whereIn('status', explode(',', $request->status));
        }
        // Stays overlapping [from, to]
        if ($request->filled('from')) {
            $query->whereDate('check_out', '>', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('check_in', '<=', $request->to);
        }
        if ($request->filled('q')) {
            $q = '%' . $request->q . '%';
            $query->where(fn ($w) => $w->where('reference', 'like', $q)
                ->orWhere('guest_name', 'like', $q)
                ->orWhere('guest_email', 'like', $q));
        }

        $page = $query->orderBy('check_in')->paginate(50);
        $page->getCollection()->transform(fn ($b) => (new BookingResource($b))->forStaff());

        return $page;
    }

    /** GET /admin/bookings/{booking} */
    public function show(Booking $booking): BookingResource
    {
        return (new BookingResource($booking->load(['roomType', 'room', 'payments', 'statusHistory'])))->forStaff();
    }

    /** POST /admin/bookings — phone / walk-in booking, confirmed immediately, pay at hotel. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'guest_name' => ['required', 'string', 'max:191'],
            'guest_email' => ['required', 'email', 'max:191'],
            'guest_phone' => ['nullable', 'string', 'max:32'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
        ]);

        $booking = $this->bookings->hold($data + ['payment_method' => 'pay_at_hotel', 'source' => 'admin'], $request->user());
        $this->payments->start($booking);

        return (new BookingResource($booking->load(['roomType', 'room', 'payments', 'statusHistory'])))
            ->forStaff()->withToken()->response()->setStatusCode(201);
    }

    /** PATCH /admin/bookings/{booking}/status { status: checked_in|checked_out|no_show, note? } */
    public function status(Request $request, Booking $booking): BookingResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT, Booking::STATUS_NO_SHOW])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $booking = $this->bookings->changeStatus($booking, $data['status'], $request->user(), $data['note'] ?? null);

        return $this->show($booking);
    }

    /** POST /admin/bookings/{booking}/cancel { reason, refund: bool, refund_amount? } */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'refund' => ['boolean'],
        ]);

        [$booking, $refunded] = $this->payments->cancelAndRefund(
            $booking, $data['reason'], (bool) ($data['refund'] ?? false), $request->user()
        );

        return response()->json(['booking' => $this->show($booking), 'refunded_amount' => $refunded]);
    }

    /** POST /admin/bookings/{booking}/refund { amount? } — partial or full refund without cancelling. */
    public function refund(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate(['amount' => ['nullable', 'numeric', 'min:0.01']]);

        $refunded = $this->payments->refund($booking, isset($data['amount']) ? (string) $data['amount'] : null, $request->user());

        if ($refunded === null) {
            throw new BookingException('Nothing refundable online for this booking.', 'nothing_to_refund');
        }

        return response()->json(['booking' => $this->show($booking->refresh()), 'refunded_amount' => $refunded]);
    }

    /** POST /admin/bookings/{booking}/mark-paid — front desk collected a pay-at-hotel payment. */
    public function markPaid(Request $request, Booking $booking): BookingResource
    {
        $payment = $booking->payments()->where('gateway', 'pay_at_hotel')->where('status', 'pending')->first();

        if (! $payment) {
            throw new BookingException('No pending pay-at-hotel payment on this booking.', 'nothing_to_collect');
        }

        $payment->update(['status' => 'paid', 'paid_at' => now(), 'gateway_status' => 'COLLECTED_BY:' . $request->user()->id]);

        return $this->show($booking);
    }
}
