<?php

namespace Tests\Feature;

use App\Models\Booking;

class StaffBookingTest extends HotelTestCase
{
    public function test_guests_cannot_reach_the_back_office(): void
    {
        $guest = $this->staff('guest');

        $this->getJson('/api/admin/bookings')->assertUnauthorized();
        $this->actingAs($guest, 'sanctum')->getJson('/api/admin/bookings')->assertForbidden();
    }

    public function test_receptionist_runs_the_front_desk_but_not_settings(): void
    {
        $this->addRooms(1);
        $receptionist = $this->staff('receptionist');

        $id = $this->actingAs($receptionist, 'sanctum')->postJson('/api/admin/bookings', [
            'room_type_id' => $this->roomType->id,
            'check_in' => $this->date(0), 'check_out' => $this->date(2),
            'adults' => 1, 'guest_name' => 'Walk In', 'guest_email' => 'walkin@example.com',
        ])->assertCreated()->assertJsonPath('status', 'confirmed')->assertJsonPath('source', 'admin')->json('reference');

        $booking = Booking::where('reference', $id)->first();

        $this->patchJson("/api/admin/bookings/{$booking->id}/status", ['status' => 'checked_in'])
            ->assertOk()->assertJsonPath('status', 'checked_in');

        $this->postJson("/api/admin/bookings/{$booking->id}/mark-paid")
            ->assertOk()->assertJsonPath('balance_due', '0.00');

        $this->patchJson("/api/admin/bookings/{$booking->id}/status", ['status' => 'checked_out'])
            ->assertOk()->assertJsonPath('status', 'checked_out')
            ->assertJsonCount(3, 'history');

        // Invalid transition
        $this->patchJson("/api/admin/bookings/{$booking->id}/status", ['status' => 'checked_in'])
            ->assertStatus(409)->assertJsonPath('code', 'invalid_state');

        $this->getJson('/api/admin/settings')->assertForbidden();
    }

    public function test_owner_updates_settings_and_public_endpoint_reflects_them(): void
    {
        $owner = $this->staff('owner');

        $this->actingAs($owner, 'sanctum')->patchJson('/api/admin/settings', [
            'name' => 'Pousada Mar Azul', 'currency' => 'eur', 'payment_methods' => ['paypal'],
        ])->assertOk()->assertJsonPath('currency', 'EUR');

        $this->getJson('/api/hotel')->assertOk()
            ->assertJsonPath('name', 'Pousada Mar Azul')
            ->assertJsonPath('payment_methods', ['paypal'])
            ->assertJsonPath('paypal.currency', 'EUR')
            ->assertJsonMissingPath('frontend_url');
    }

    public function test_disabled_payment_method_is_rejected_at_checkout(): void
    {
        config(['hotel.payment_methods' => ['paypal']]);
        $this->addRooms(1);

        $this->postJson('/api/bookings', $this->bookingPayload())
            ->assertStatus(422)->assertJsonPath('code', 'payment_method_disabled');
    }

    public function test_admin_cancel_frees_the_room(): void
    {
        $this->addRooms(1);
        $admin = $this->staff('admin');
        $ref = $this->postJson('/api/bookings', $this->bookingPayload())->json('reference');
        $booking = Booking::where('reference', $ref)->first();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/bookings/{$booking->id}/cancel", ['reason' => 'Guest called'])
            ->assertOk()->assertJsonPath('booking.status', 'cancelled');

        $this->postJson('/api/bookings', $this->bookingPayload(['guest_email' => 'x@example.com']))->assertCreated();
    }
}
