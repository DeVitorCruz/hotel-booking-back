<?php

namespace Tests\Feature;

use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\RoomNight;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class PayPalBookingTest extends HotelTestCase
{
    private function createPayPalBooking(array $overrides = []): array
    {
        $res = $this->postJson('/api/bookings', $this->bookingPayload(['payment_method' => 'paypal'] + $overrides))
            ->assertCreated();

        return [$res->json('reference'), $res->json('manage_token'), $res];
    }

    public function test_paypal_booking_holds_room_and_returns_order_for_js_sdk(): void
    {
        $this->addRooms(1);
        $this->fakePayPal();

        [, , $res] = $this->createPayPalBooking();

        $res->assertJsonPath('status', 'pending')
            ->assertJsonPath('amount_due_now', '300.00')
            ->assertJsonPath('payment.paypal_order_id', 'ORDER-1')
            ->assertJsonPath('payment.approve_url', 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1');

        $this->assertNotNull($res->json('hold_expires_at'));
        $this->assertSame(3, RoomNight::count());

        // Order sent to PayPal with our amount and reference
        Http::assertSent(fn ($r) => $r->url() === self::PP . '/v2/checkout/orders'
            && $r['purchase_units'][0]['amount'] === ['currency_code' => 'USD', 'value' => '300.00']
            && $r['purchase_units'][0]['custom_id'] === $res->json('reference'));

        Mail::assertNotQueued(BookingConfirmedMail::class);
    }

    public function test_capture_confirms_booking_and_is_idempotent(): void
    {
        $this->addRooms(1);
        $this->fakePayPal();
        [$ref, $token] = $this->createPayPalBooking();

        $this->withHeader('X-Booking-Token', $token)
            ->postJson("/api/bookings/{$ref}/paypal/capture", ['order_id' => 'ORDER-1'])
            ->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('amount_paid', '300.00')
            ->assertJsonPath('balance_due', '0.00');

        $booking = Booking::where('reference', $ref)->first();
        $this->assertSame('CAP-ORDER-1', $booking->payments()->first()->gateway_capture_id);
        $this->assertNull($booking->hold_expires_at);

        // Second call (double click / retry) does not hit PayPal again
        $this->withHeader('X-Booking-Token', $token)
            ->postJson("/api/bookings/{$ref}/paypal/capture", ['order_id' => 'ORDER-1'])
            ->assertOk()->assertJsonPath('status', 'confirmed');

        $this->assertSame(1, $this->paypalCalls('/capture'));
        Mail::assertQueued(BookingConfirmedMail::class, 1);
    }

    public function test_declined_card_keeps_booking_on_hold_and_retry_creates_new_order(): void
    {
        $this->addRooms(1);
        $this->fakePayPal('ORDER-1', 'DECLINED');
        [$ref, $token] = $this->createPayPalBooking();

        $this->withHeader('X-Booking-Token', $token)
            ->postJson("/api/bookings/{$ref}/paypal/capture", ['order_id' => 'ORDER-1'])
            ->assertStatus(402)->assertJsonPath('code', 'payment_failed');

        $this->assertSame('pending', Booking::where('reference', $ref)->value('status'));

        $this->withHeader('X-Booking-Token', $token)
            ->postJson("/api/bookings/{$ref}/paypal/retry")
            ->assertOk()->assertJsonPath('payment.paypal_order_id', 'ORDER-1');

        $this->assertSame(2, Booking::where('reference', $ref)->first()->payments()->count());
    }

    public function test_unpaid_hold_expires_and_room_becomes_available_again(): void
    {
        $this->addRooms(1);
        $this->fakePayPal();
        [$ref] = $this->createPayPalBooking();

        $this->travel(16)->minutes();
        $this->artisan('bookings:release-expired-holds')->assertSuccessful();

        $booking = Booking::where('reference', $ref)->first();
        $this->assertSame('expired', $booking->status);
        $this->assertSame(0, RoomNight::count());
        $this->assertSame('failed', $booking->payments()->first()->status);

        $this->postJson('/api/bookings', $this->bookingPayload(['guest_email' => 'next@example.com']))->assertCreated();
    }

    public function test_capture_after_expiry_reclaims_room_when_still_free(): void
    {
        $this->addRooms(1);
        $this->fakePayPal();
        [$ref, $token] = $this->createPayPalBooking();

        $this->travel(16)->minutes();
        $this->artisan('bookings:release-expired-holds');

        $this->withHeader('X-Booking-Token', $token)
            ->postJson("/api/bookings/{$ref}/paypal/capture", ['order_id' => 'ORDER-1'])
            ->assertOk()->assertJsonPath('status', 'confirmed');

        $this->assertSame(3, RoomNight::count());
    }

    public function test_capture_after_expiry_is_refused_without_charging_when_room_was_resold(): void
    {
        $this->addRooms(1);
        $this->fakePayPal();
        [$ref, $token] = $this->createPayPalBooking();

        $this->travel(16)->minutes();
        $this->postJson('/api/bookings', $this->bookingPayload(['guest_email' => 'other@example.com']))->assertCreated();

        $this->withHeader('X-Booking-Token', $token)
            ->postJson("/api/bookings/{$ref}/paypal/capture", ['order_id' => 'ORDER-1'])
            ->assertStatus(409)->assertJsonPath('code', 'hold_expired');

        $this->assertSame(0, $this->paypalCalls('/capture'), 'Guest must not be charged');
    }

    public function test_manage_endpoints_require_the_booking_token(): void
    {
        $this->addRooms(1);
        $this->fakePayPal();
        [$ref, $token] = $this->createPayPalBooking();

        $this->getJson("/api/bookings/{$ref}")->assertNotFound();
        $this->withHeader('X-Booking-Token', 'wrong')->getJson("/api/bookings/{$ref}")->assertNotFound();
        $this->getJson("/api/bookings/{$ref}?token={$token}")->assertOk()->assertJsonMissingPath('manage_token');
        $this->postJson("/api/bookings/{$ref}/cancel")->assertNotFound();
    }

    public function test_guest_cancel_in_free_window_refunds_via_paypal_and_frees_room(): void
    {
        $this->addRooms(1);
        $this->fakePayPal();
        [$ref, $token] = $this->createPayPalBooking();
        $this->withHeader('X-Booking-Token', $token)
            ->postJson("/api/bookings/{$ref}/paypal/capture", ['order_id' => 'ORDER-1'])->assertOk();

        $this->withHeader('X-Booking-Token', $token)
            ->postJson("/api/bookings/{$ref}/cancel")
            ->assertOk()
            ->assertJsonPath('free_cancellation', true)
            ->assertJsonPath('refunded_amount', '300.00')
            ->assertJsonPath('booking.status', 'cancelled')
            ->assertJsonPath('booking.amount_refunded', '300.00');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/payments/captures/CAP-ORDER-1/refund')
            && $r['amount'] === ['value' => '300.00', 'currency_code' => 'USD']);

        $this->assertSame(0, RoomNight::count());
        Mail::assertQueued(BookingCancelledMail::class, 1);
    }

    public function test_late_guest_cancel_does_not_refund(): void
    {
        $this->addRooms(1);
        $this->fakePayPal();
        [$ref, $token] = $this->createPayPalBooking(['check_in' => $this->date(1), 'check_out' => $this->date(3)]);
        $this->withHeader('X-Booking-Token', $token)
            ->postJson("/api/bookings/{$ref}/paypal/capture", ['order_id' => 'ORDER-1'])->assertOk();

        $this->withHeader('X-Booking-Token', $token)
            ->postJson("/api/bookings/{$ref}/cancel")
            ->assertOk()
            ->assertJsonPath('free_cancellation', false)
            ->assertJsonPath('refunded_amount', null);

        $this->assertSame(0, $this->paypalCalls('/refund'));
    }

    public function test_webhook_rejects_bad_signature(): void
    {
        $this->fakePayPal(extra: [
            self::PP . '/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE']),
        ]);

        $this->postJson('/api/webhooks/paypal', ['event_type' => 'CHECKOUT.ORDER.APPROVED'], $this->webhookHeaders())
            ->assertStatus(400);
    }

    public function test_order_approved_webhook_captures_when_guest_closed_the_tab(): void
    {
        $this->addRooms(1);
        $this->fakePayPal();
        [$ref] = $this->createPayPalBooking();

        $this->postJson('/api/webhooks/paypal', [
            'id' => 'WH-1', 'event_type' => 'CHECKOUT.ORDER.APPROVED', 'resource' => ['id' => 'ORDER-1', 'status' => 'APPROVED'],
        ], $this->webhookHeaders())->assertOk();

        $this->assertSame('confirmed', Booking::where('reference', $ref)->value('status'));

        // The PAYMENT.CAPTURE.COMPLETED that follows is a harmless echo
        $this->postJson('/api/webhooks/paypal', [
            'id' => 'WH-2', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAP-ORDER-1', 'status' => 'COMPLETED',
                'amount' => ['value' => '300.00', 'currency_code' => 'USD'],
                'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-1']],
            ],
        ], $this->webhookHeaders())->assertOk();

        Mail::assertQueued(BookingConfirmedMail::class, 1);
    }

    public function test_refund_webhook_echo_is_not_double_counted(): void
    {
        $this->addRooms(1);
        $this->fakePayPal();
        [$ref, $token] = $this->createPayPalBooking();
        $this->withHeader('X-Booking-Token', $token)->postJson("/api/bookings/{$ref}/paypal/capture", ['order_id' => 'ORDER-1']);

        $admin = $this->staff('admin');
        $booking = Booking::where('reference', $ref)->first();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/bookings/{$booking->id}/refund", ['amount' => 100])
            ->assertOk(); // fake returns REF-1

        $this->postJson('/api/webhooks/paypal', [
            'id' => 'WH-3', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'REF-1', 'status' => 'COMPLETED', 'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                'links' => [['rel' => 'up', 'href' => self::PP . '/v2/payments/captures/CAP-ORDER-1']],
            ],
        ], $this->webhookHeaders())->assertOk();

        $payment = $booking->payments()->first();
        $this->assertSame('100.00', $payment->refunded_amount);
        $this->assertSame('partially_refunded', $payment->status);
    }

    private function webhookHeaders(): array
    {
        return [
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-CERT-URL' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT',
            'PAYPAL-TRANSMISSION-ID' => 'tx-1',
            'PAYPAL-TRANSMISSION-SIG' => 'sig',
            'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
        ];
    }
}
