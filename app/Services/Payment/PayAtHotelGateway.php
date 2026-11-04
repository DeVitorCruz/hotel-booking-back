<?php

namespace App\Services\Payment;

use App\Contracts\Payable;
use Illuminate\Http\Request;
use LogicException;

/**
 * Offline "gateway": the guest pays at the front desk.
 * The Payment row records the amount to collect; staff mark it paid at check-in.
 */
class PayAtHotelGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'pay_at_hotel';
    }

    public function createPayment(Payable $payable, array $options = []): PaymentResult
    {
        return new PaymentResult(
            gatewayId: 'HOTEL-' . $payable->payableReference(),
            status: 'pending',
            paymentDetails: ['instructions' => 'Pay at the front desk on arrival (cash or card).'],
        );
    }

    public function capturePayment(string $gatewayId): PaymentResult
    {
        throw new LogicException('Pay-at-hotel payments are collected at the front desk.');
    }

    public function getPaymentStatus(string $gatewayId): PaymentResult
    {
        return new PaymentResult(gatewayId: $gatewayId, status: 'pending');
    }

    public function refundPayment(string $captureId, string $amount, string $currency): RefundResult
    {
        throw new LogicException('Refund pay-at-hotel payments at the front desk.');
    }

    public function validateWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        return null;
    }
}
