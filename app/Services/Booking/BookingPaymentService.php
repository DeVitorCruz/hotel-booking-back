<?php

namespace App\Services\Booking;

use App\Exceptions\BookingException;
use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\Hotel\HotelSettings;
use App\Services\Payment\GatewayFactory;
use App\Services\Payment\PaymentResult;
use App\Services\Payment\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Glue between bookings and payment gateways:
 * start a payment, capture it, react to webhooks, refund on cancellation.
 */
class BookingPaymentService
{
    /** Extra minutes granted while a capture is in flight so the hold can't lapse mid-payment. */
    private const CAPTURE_GRACE_MINUTES = 5;

    public function __construct(
        private BookingService $bookings,
        private HotelSettings $settings,
    ) {}

    /**
     * Create the Payment record (and the PayPal order when paying online).
     */
    public function start(Booking $booking, array $options = []): Payment
    {
        $gatewayKey = $booking->payment_method === 'paypal' && (float) $booking->amount_due_now > 0
            ? 'paypal'
            : 'pay_at_hotel';

        if ($gatewayKey === 'paypal' && ! $booking->isPending()) {
            throw BookingException::invalidState('Only bookings awaiting payment can start a PayPal payment.');
        }

        $gateway = GatewayFactory::make($gatewayKey);

        $result = $gateway->createPayment($booking->loadMissing('roomType'), $options + [
            'brand_name' => $this->settings->get('name'),
        ]);

        return $booking->payments()->create([
            'gateway' => $gatewayKey,
            'method' => $gatewayKey === 'paypal' ? 'paypal' : 'cash_or_card_at_hotel',
            'gateway_id' => $result->gatewayId,
            'gateway_status' => $result->gatewayStatus,
            // Pay at hotel records the whole stay as the amount to collect on arrival.
            'amount' => $gatewayKey === 'paypal' ? $booking->amount_due_now : $booking->total,
            'currency' => $booking->currency,
            'status' => 'pending',
            'gateway_response' => $result->rawResponse,
            'payment_details' => $result->paymentDetails,
            'expires_at' => $booking->hold_expires_at,
        ]);
    }

    /**
     * Capture an approved PayPal order and confirm the booking. Idempotent.
     */
    public function capture(Booking $booking, string $paypalOrderId): Booking
    {
        $payment = $booking->payments()
            ->where('gateway', 'paypal')
            ->where('gateway_id', $paypalOrderId)
            ->first();

        if (! $payment) {
            throw new BookingException('Unknown PayPal order for this booking.', 'unknown_payment', 404);
        }

        if ($payment->isPaid()) {
            return $booking->refresh();
        }

        $this->extendHoldForCapture($booking);

        $result = GatewayFactory::make('paypal')->capturePayment($paypalOrderId);

        return $this->applyResult($payment, $result);
    }

    /**
     * Start a new PayPal order for a booking still on hold (e.g. card declined, popup closed).
     */
    public function retry(Booking $booking, array $options = []): Payment
    {
        if ($booking->isHoldExpired() || $booking->status === Booking::STATUS_EXPIRED) {
            throw BookingException::holdExpired();
        }

        $booking->payments()->where('gateway', 'paypal')->where('status', 'pending')
            ->update(['status' => 'failed', 'gateway_status' => 'SUPERSEDED']);

        return $this->start($booking, $options);
    }

    /**
     * Refund the paid amount (or part of it) through the gateway.
     *
     * @return string|null refunded amount, or null when nothing was refundable online
     */
    public function refund(Booking $booking, ?string $amount = null, ?User $actor = null): ?string
    {
        /** @var Payment|null $payment */
        $payment = $booking->payments()->where('gateway', 'paypal')->where('status', 'paid')->latest('id')->first();

        if (! $payment || ! $payment->gateway_capture_id) {
            return null;
        }

        $refundable = PriceCalculator::toCents($payment->amount) - PriceCalculator::toCents($payment->refunded_amount);
        $cents = $amount === null ? $refundable : min($refundable, PriceCalculator::toCents($amount));

        if ($cents <= 0) {
            return null;
        }

        $value = Quote::money($cents);
        $result = GatewayFactory::make('paypal')->refundPayment($payment->gateway_capture_id, $value, $payment->currency);

        if (! $result->succeeded()) {
            throw BookingException::payment('PayPal refused the refund. Please refund from the PayPal dashboard.');
        }

        $this->recordRefund($payment, $cents, $result->refundId);

        Log::info('Booking refunded', ['reference' => $booking->reference, 'amount' => $value, 'by' => $actor?->id]);

        return $value;
    }

    /**
     * Cancel, optionally refund, then email the guest once (with the refund amount).
     *
     * @return array{0: Booking, 1: string|null} booking and refunded amount
     */
    public function cancelAndRefund(Booking $booking, string $reason, bool $refund, ?User $actor = null): array
    {
        $booking = $this->bookings->cancel($booking, $reason, $actor, notifyGuest: false);

        $refunded = null;
        if ($refund) {
            try {
                $refunded = $this->refund($booking, actor: $actor);
            } catch (\RuntimeException $e) {
                // Cancellation stands; staff finish the refund from the PayPal dashboard.
                report($e);
            }
        }

        Mail::to($booking->guest_email)->queue(new BookingCancelledMail($booking->refresh()));

        return [$booking, $refunded];
    }

    /**
     * Handle a verified gateway webhook.
     */
    public function handleWebhook(string $gatewayKey, Request $request): void
    {
        $gateway = GatewayFactory::make($gatewayKey);

        if (! $gateway->validateWebhook($request)) {
            Log::warning("Invalid {$gatewayKey} webhook signature");
            throw new BookingException('Invalid webhook signature.', 'invalid_signature', 400);
        }

        $event = $gateway->parseWebhook($request);

        if (! $event) {
            return; // event type we don't care about
        }

        $payment = $this->findPayment($gatewayKey, $event);

        if (! $payment) {
            Log::warning('Webhook for unknown payment', ['type' => $event->type, 'event' => $event->eventId]);

            return;
        }

        /** @var Booking $booking */
        $booking = $payment->payable;

        match ($event->status) {
            // Buyer approved but our capture call never arrived (closed tab): capture now.
            'approved' => $payment->isPaid() ? null : $this->capture($booking, $payment->gateway_id),

            'paid' => $this->applyResult($payment, new PaymentResult(
                gatewayId: $payment->gateway_id,
                status: 'paid',
                captureId: $event->captureId,
                amount: $event->amount,
                currency: $event->currency,
                gatewayStatus: 'COMPLETED',
            )),

            'failed' => $payment->update(['status' => 'failed', 'gateway_status' => $event->type]),

            'refunded' => $event->amount
                ? $this->recordRefund($payment, PriceCalculator::toCents($event->amount), $event->refundId ?? $event->eventId)
                : null,

            default => null,
        };
    }

    // --- internals ------------------------------------------------------

    private function applyResult(Payment $payment, PaymentResult $result): Booking
    {
        /** @var Booking $booking */
        $booking = $payment->payable;

        if ($result->isPaid()) {
            $expected = PriceCalculator::toCents($payment->amount);
            $received = PriceCalculator::toCents($result->amount ?? '0');

            if ($received !== $expected || strtoupper((string) $result->currency) !== $payment->currency) {
                Log::critical('PayPal amount mismatch', [
                    'reference' => $booking->reference,
                    'expected' => $payment->amount . ' ' . $payment->currency,
                    'received' => $result->amount . ' ' . $result->currency,
                ]);
            }

            $alreadyPaid = DB::transaction(function () use ($payment, $result) {
                $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                if ($locked->isPaid()) {
                    return true;
                }
                $locked->update([
                    'status' => 'paid',
                    'gateway_status' => $result->gatewayStatus,
                    'gateway_capture_id' => $result->captureId,
                    'gateway_response' => $result->rawResponse ?: $locked->gateway_response,
                    'payment_details' => array_merge($locked->payment_details ?? [], $result->paymentDetails),
                    'paid_at' => now(),
                ]);

                return false;
            });

            if ($alreadyPaid) {
                return $booking->refresh();
            }

            try {
                return $this->bookings->confirm($booking, 'PayPal payment captured.');
            } catch (BookingException $e) {
                // Money taken but the room is gone (hold expired and resold): give it back.
                Log::error('Paid booking could not be confirmed, refunding', ['reference' => $booking->reference, 'error' => $e->getMessage()]);
                $this->refund($booking->refresh());
                $booking->forceFill([
                    'status' => Booking::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Room no longer available after payment; refunded automatically.',
                ])->save();

                throw $e;
            }
        }

        $payment->update([
            'status' => $result->isFailed() ? 'failed' : 'pending',
            'gateway_status' => $result->gatewayStatus,
            'payment_details' => array_merge($payment->payment_details ?? [], $result->paymentDetails),
        ]);

        if ($result->isFailed()) {
            throw BookingException::payment('PayPal declined the payment. Please try another funding source.');
        }

        // Capture is PENDING at PayPal (e.g. eCheck / review): keep the room held until the webhook says COMPLETED.
        if ($result->isPending() && $booking->isPending()) {
            $booking->forceFill(['hold_expires_at' => null])->save();
        }

        return $booking->refresh();
    }

    private function extendHoldForCapture(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($locked->status === Booking::STATUS_CANCELLED) {
                throw BookingException::invalidState('This booking was cancelled.');
            }

            // Hold lapsed and nights were released: take the room back if nobody else did.
            if ($locked->status === Booking::STATUS_EXPIRED) {
                if (! $this->bookings->reclaim($locked, self::CAPTURE_GRACE_MINUTES)) {
                    throw BookingException::holdExpired();
                }

                return;
            }

            // Still pending (even if a few seconds past the deadline): nights are ours, extend.
            if ($locked->isPending() && $locked->hold_expires_at) {
                $grace = now()->addMinutes(self::CAPTURE_GRACE_MINUTES);
                if ($locked->hold_expires_at->lt($grace)) {
                    $locked->forceFill(['hold_expires_at' => $grace])->save();
                }
            }
        });
    }

    private function recordRefund(Payment $payment, int $cents, string $refundId): void
    {
        DB::transaction(function () use ($payment, $cents, $refundId) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $details = $locked->payment_details ?? [];
            $known = $details['refund_ids'] ?? [];

            // Our own refund call already recorded it; the webhook is just the echo.
            if (in_array($refundId, $known, true)) {
                return;
            }
            $details['refund_ids'] = [...$known, $refundId];

            $refunded = min(
                PriceCalculator::toCents($locked->amount),
                PriceCalculator::toCents($locked->refunded_amount) + $cents
            );

            $locked->update([
                'payment_details' => $details,
                'refunded_amount' => Quote::money($refunded),
                'status' => $refunded >= PriceCalculator::toCents($locked->amount) ? 'refunded' : 'partially_refunded',
            ]);
        });
    }

    private function findPayment(string $gatewayKey, WebhookEvent $event): ?Payment
    {
        $query = Payment::query()->where('gateway', $gatewayKey);

        if ($event->gatewayId) {
            return (clone $query)->where('gateway_id', $event->gatewayId)->first();
        }

        if ($event->captureId) {
            return (clone $query)->where('gateway_capture_id', $event->captureId)->first();
        }

        return null;
    }
}
