<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Booking */
class BookingResource extends JsonResource
{
    /** Include the manage token (only right after creation, to the creator). */
    public bool $withToken = false;

    /** Include staff-only fields. */
    public bool $forStaff = false;

    public function toArray(Request $request): array
    {
        $paidCents = (int) round($this->payments->where('status', 'paid')->sum('amount') * 100)
            + (int) round($this->payments->where('status', 'partially_refunded')->sum('amount') * 100);
        $refundedCents = (int) round($this->payments->sum('refunded_amount') * 100);
        $payment = $this->payments->sortByDesc('id')->first();

        return array_filter([
            'id' => $this->forStaff ? $this->id : null,
            'reference' => $this->reference,
            'manage_token' => $this->withToken ? $this->manage_token : null,
            'status' => $this->status,
            'room_type' => new RoomTypeResource($this->whenLoaded('roomType')),
            'room_id' => $this->forStaff ? $this->room_id : null,
            'room_number' => $this->forStaff ? $this->room?->number : null,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->guest_email,
            'guest_phone' => $this->guest_phone,
            'adults' => $this->adults,
            'children' => $this->children,
            'special_requests' => $this->special_requests,
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'nights' => $this->nights,
            'currency' => $this->currency,
            'nightly_rates' => $this->nightly_rates,
            'subtotal' => $this->subtotal,
            'taxes' => $this->taxes,
            'total' => $this->total,
            'amount_due_now' => $this->amount_due_now,
            'amount_paid' => number_format($paidCents / 100, 2, '.', ''),
            'amount_refunded' => number_format($refundedCents / 100, 2, '.', ''),
            'balance_due' => number_format(max(0, (int) round($this->total * 100) - $paidCents) / 100, 2, '.', ''),
            'payment_method' => $this->payment_method,
            'payment' => $payment ? [
                'gateway' => $payment->gateway,
                'status' => $payment->status,
                'paypal_order_id' => $payment->gateway === 'paypal' && $payment->isPending() ? $payment->gateway_id : null,
                'approve_url' => $payment->isPending() ? ($payment->payment_details['approve_url'] ?? null) : null,
                'instructions' => $payment->payment_details['instructions'] ?? null,
            ] : null,
            'hold_expires_at' => $this->hold_expires_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'source' => $this->forStaff ? $this->source : null,
            'history' => $this->forStaff ? $this->statusHistory->map(fn ($h) => [
                'from' => $h->from_status, 'to' => $h->to_status, 'note' => $h->note,
                'by' => $h->changed_by, 'at' => $h->created_at?->toIso8601String(),
            ]) : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ], fn ($v) => $v !== null);
    }

    public function withToken(): static
    {
        $this->withToken = true;

        return $this;
    }

    public function forStaff(): static
    {
        $this->forStaff = true;

        return $this;
    }
}
