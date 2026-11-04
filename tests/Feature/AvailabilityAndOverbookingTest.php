<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\RateOverride;
use App\Models\RoomNight;
use App\Models\RoomType;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class AvailabilityAndOverbookingTest extends HotelTestCase
{
    public function test_availability_lists_fitting_room_types_with_free_count_and_quote(): void
    {
        $this->addRooms(2);
        $suite = RoomType::create(['name' => 'Suite', 'slug' => 'suite', 'base_price' => 250, 'max_adults' => 4, 'max_children' => 2, 'max_occupancy' => 5]);
        $this->addRooms(1, $suite);

        $res = $this->getJson('/api/availability?' . http_build_query([
            'check_in' => $this->date(5), 'check_out' => $this->date(7), 'adults' => 2,
        ]))->assertOk();

        $res->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.room_type.slug', 'deluxe')
            ->assertJsonPath('results.0.available', 2)
            ->assertJsonPath('results.0.quote.nights', 2)
            ->assertJsonPath('results.0.quote.total', '200.00');

        // 4 adults only fit the suite
        $this->getJson('/api/availability?' . http_build_query([
            'check_in' => $this->date(5), 'check_out' => $this->date(7), 'adults' => 4,
        ]))->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.room_type.slug', 'suite');
    }

    public function test_rejects_past_and_inverted_dates(): void
    {
        $this->getJson('/api/availability?' . http_build_query([
            'check_in' => $this->date(-1), 'check_out' => $this->date(2), 'adults' => 2,
        ]))->assertStatus(422)->assertJsonPath('code', 'invalid_dates');

        $this->getJson('/api/availability?' . http_build_query([
            'check_in' => $this->date(5), 'check_out' => $this->date(4), 'adults' => 2,
        ]))->assertStatus(422);
    }

    public function test_pay_at_hotel_booking_is_confirmed_reserves_nights_and_emails_guest(): void
    {
        $this->addRooms(1);

        $res = $this->postJson('/api/bookings', $this->bookingPayload())->assertCreated();

        $res->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('total', '300.00')
            ->assertJsonPath('amount_due_now', '0.00')
            ->assertJsonPath('balance_due', '300.00')
            ->assertJsonPath('payment.gateway', 'pay_at_hotel');

        $this->assertNotEmpty($res->json('manage_token'));
        $this->assertSame(3, RoomNight::count());
        Mail::assertQueued(BookingConfirmedMail::class, fn ($m) => $m->hasTo('ana@example.com'));
    }

    public function test_same_room_cannot_be_sold_twice_for_overlapping_nights(): void
    {
        $this->addRooms(1);

        $this->postJson('/api/bookings', $this->bookingPayload())->assertCreated();

        // Exact same dates
        $this->postJson('/api/bookings', $this->bookingPayload(['guest_email' => 'b@example.com']))
            ->assertStatus(409)->assertJsonPath('code', 'no_availability');

        // Partial overlap (last night of the first stay)
        $this->postJson('/api/bookings', $this->bookingPayload([
            'check_in' => $this->date(12), 'check_out' => $this->date(14),
        ]))->assertStatus(409);

        // Back-to-back: check in the day the first guest checks out
        $this->postJson('/api/bookings', $this->bookingPayload([
            'check_in' => $this->date(13), 'check_out' => $this->date(15),
        ]))->assertCreated();

        $this->assertSame(2, Booking::count());
    }

    public function test_database_unique_index_rejects_duplicate_room_night(): void
    {
        $this->addRooms(1);
        $roomId = $this->roomType->rooms()->value('id');

        RoomNight::create(['room_id' => $roomId, 'date' => $this->date(3)]);

        $this->expectException(UniqueConstraintViolationException::class);
        RoomNight::create(['room_id' => $roomId, 'date' => $this->date(3)]);
    }

    public function test_concurrent_request_falls_back_to_next_free_room(): void
    {
        $this->addRooms(2);
        [$first, $second] = $this->roomType->rooms()->orderBy('id')->pluck('id')->all();

        // Another request grabbed room #1 after our availability check ran (stale list).
        RoomNight::insert(collect(range(10, 12))->map(fn ($d) => [
            'room_id' => $first, 'date' => $this->date($d),
        ])->all());

        $this->app->instance(AvailabilityService::class, new class(
            app(\App\Services\Hotel\HotelSettings::class),
            app(\App\Services\Booking\PriceCalculator::class),
            app(\App\Services\Booking\HoldReleaser::class),
        ) extends AvailabilityService {
            public function freeRoomIds(RoomType $roomType, CarbonImmutable $checkIn, CarbonImmutable $checkOut): Collection
            {
                return $roomType->rooms()->orderBy('id')->pluck('id'); // pretends both are free
            }
        });
        $this->app->forgetInstance(BookingService::class);

        $res = $this->postJson('/api/bookings', $this->bookingPayload())->assertCreated();

        $this->assertSame($second, Booking::where('reference', $res->json('reference'))->value('room_id'));
    }

    public function test_seasonal_rate_tax_and_deposit_are_applied(): void
    {
        config(['hotel.tax_percent' => 10, 'hotel.deposit_percent' => 30]);
        $this->addRooms(1);

        RateOverride::create([
            'room_type_id' => $this->roomType->id, 'label' => 'High season',
            'start_date' => $this->date(11), 'end_date' => $this->date(11), 'price' => 150,
        ]);

        // nights: 100 + 150 + 100 = 350, tax 35 → 385, deposit 30% = 115.50
        $res = $this->getJson('/api/availability?' . http_build_query([
            'check_in' => $this->date(10), 'check_out' => $this->date(13), 'adults' => 2,
        ]))->assertOk();

        $res->assertJsonPath('results.0.quote.subtotal', '350.00')
            ->assertJsonPath('results.0.quote.taxes', '35.00')
            ->assertJsonPath('results.0.quote.total', '385.00')
            ->assertJsonPath('results.0.quote.amount_due_now', '115.50')
            ->assertJsonPath('results.0.quote.nightly_rates.1.price', '150.00');
    }

    public function test_min_nights_from_rate_override_is_enforced(): void
    {
        $this->addRooms(1);
        RateOverride::create([
            'room_type_id' => $this->roomType->id, 'start_date' => $this->date(10), 'end_date' => $this->date(20),
            'price' => 120, 'min_nights' => 4,
        ]);

        $this->postJson('/api/bookings', $this->bookingPayload())
            ->assertStatus(422)->assertJsonPath('code', 'min_nights');
    }

    public function test_party_too_large_for_room_type_is_rejected(): void
    {
        $this->addRooms(1);

        $this->postJson('/api/bookings', $this->bookingPayload(['adults' => 3, 'children' => 1]))
            ->assertStatus(422)->assertJsonPath('code', 'too_many_guests');
    }

    public function test_room_block_removes_room_from_sale_and_cannot_overlap_bookings(): void
    {
        $this->addRooms(1);
        $roomId = $this->roomType->rooms()->value('id');
        $admin = $this->staff('admin');

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/room-blocks', [
            'room_id' => $roomId, 'start_date' => $this->date(10), 'end_date' => $this->date(12), 'reason' => 'Painting',
        ])->assertCreated();

        $this->postJson('/api/bookings', $this->bookingPayload())->assertStatus(409);

        // Booking after the block, then a block overlapping that booking fails
        $this->postJson('/api/bookings', $this->bookingPayload([
            'check_in' => $this->date(20), 'check_out' => $this->date(22),
        ]))->assertCreated();

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/room-blocks', [
            'room_id' => $roomId, 'start_date' => $this->date(21), 'end_date' => $this->date(25),
        ])->assertStatus(409)->assertJsonPath('code', 'dates_taken');
    }
}
