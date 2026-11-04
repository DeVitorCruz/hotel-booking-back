<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdminManagementTest extends HotelTestCase
{
    public function test_owner_creates_room_type_with_amenities_rooms_and_it_becomes_bookable(): void
    {
        $owner = $this->staff('owner');
        $wifi = Amenity::create(['name' => 'Wi-Fi', 'slug' => 'wifi', 'icon' => 'wifi']);

        $type = $this->actingAs($owner, 'sanctum')->postJson('/api/admin/room-types', [
            'name' => 'Garden Suite', 'base_price' => 180, 'max_adults' => 2, 'max_occupancy' => 3,
            'max_children' => 1, 'amenity_ids' => [$wifi->id],
        ])->assertCreated()
            ->assertJsonPath('slug', 'garden-suite')
            ->assertJsonPath('amenity_ids', [$wifi->id])
            ->assertJsonPath('rooms_count', 0)
            ->json();

        // Same name again gets a unique slug
        $this->postJson('/api/admin/room-types', [
            'name' => 'Garden Suite', 'base_price' => 1, 'max_adults' => 1, 'max_occupancy' => 1,
        ])->assertCreated()->assertJsonPath('slug', 'garden-suite-2');

        $this->postJson('/api/admin/rooms', ['room_type_id' => $type['id'], 'number' => 'G1', 'floor' => 'Ground'])
            ->assertCreated()->assertJsonPath('status', 'active')->assertJsonPath('room_type', 'Garden Suite');
        $this->postJson('/api/admin/rooms', ['room_type_id' => $type['id'], 'number' => 'G1'])->assertStatus(422);

        $this->getJson('/api/availability?' . http_build_query([
            'check_in' => $this->date(3), 'check_out' => $this->date(5), 'adults' => 2,
        ]))->assertOk()->assertJsonFragment(['slug' => 'garden-suite']);

        // Deactivate → hidden from website
        $this->patchJson("/api/admin/room-types/{$type['id']}", ['is_active' => false])->assertOk()->assertJsonPath('is_active', false);
        $this->getJson('/api/room-types/garden-suite')->assertNotFound();
    }

    public function test_room_type_and_room_with_bookings_cannot_be_deleted(): void
    {
        $this->addRooms(1);
        $admin = $this->staff('admin');
        $this->postJson('/api/bookings', $this->bookingPayload())->assertCreated();
        $room = Room::first();

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/room-types/{$this->roomType->id}")
            ->assertStatus(409)->assertJsonPath('code', 'has_bookings');
        $this->deleteJson("/api/admin/rooms/{$room->id}")->assertStatus(409);

        $other = RoomType::create(['name' => 'Other', 'slug' => 'other', 'base_price' => 50, 'max_adults' => 2, 'max_occupancy' => 2]);
        $this->patchJson("/api/admin/rooms/{$room->id}", ['room_type_id' => $other->id])->assertStatus(409);

        // Maintenance takes the room off sale
        $this->patchJson("/api/admin/rooms/{$room->id}", ['status' => 'maintenance'])->assertOk();
        $this->postJson('/api/bookings', $this->bookingPayload([
            'check_in' => $this->date(30), 'check_out' => $this->date(31), 'guest_email' => 'z@example.com',
        ]))->assertStatus(409);

        $this->deleteJson("/api/admin/room-types/{$other->id}")->assertNoContent();
    }

    public function test_room_type_photo_upload_order_and_delete(): void
    {
        Storage::fake('public');
        $admin = $this->staff('admin');
        $id = $this->roomType->id;

        $first = $this->actingAs($admin, 'sanctum')->post("/api/admin/room-types/{$id}/images", [
            'image' => UploadedFile::fake()->image('room.jpg', 1200, 800), 'alt' => 'Bed',
        ], ['Accept' => 'application/json'])->assertCreated()->json();

        Storage::disk('public')->assertExists("room-types/{$id}/" . basename($first['url']));

        $second = $this->postJson("/api/admin/room-types/{$id}/images", ['url' => 'https://cdn.example.com/a.jpg'])
            ->assertCreated()->json();

        $this->patchJson("/api/admin/room-types/{$id}/images/order", ['ids' => [$second['id'], $first['id']]])
            ->assertOk()->assertJsonPath('images.0.id', $second['id']);

        $this->getJson('/api/room-types/deluxe')->assertJsonPath('images.0.url', 'https://cdn.example.com/a.jpg');

        $this->deleteJson("/api/admin/room-types/{$id}/images/{$first['id']}")->assertNoContent();
        Storage::disk('public')->assertMissing("room-types/{$id}/" . basename($first['url']));

        $this->post("/api/admin/room-types/{$id}/images", [
            'image' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_seasonal_rate_crud_changes_the_quote(): void
    {
        $this->addRooms(1);
        $admin = $this->staff('admin');

        $rate = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/rates', [
            'room_type_id' => $this->roomType->id, 'label' => 'Carnival',
            'start_date' => $this->date(10), 'end_date' => $this->date(12), 'price' => 160,
        ])->assertCreated()->assertJsonPath('price', '160.00')->json();

        $quote = fn () => $this->getJson('/api/availability?' . http_build_query([
            'check_in' => $this->date(10), 'check_out' => $this->date(12), 'adults' => 1,
        ]))->json('results.0.quote.total');

        $this->assertSame('320.00', $quote());

        $this->patchJson("/api/admin/rates/{$rate['id']}", ['price' => 120])->assertOk();
        $this->assertSame('240.00', $quote());

        $this->patchJson("/api/admin/rates/{$rate['id']}", ['end_date' => $this->date(5)])->assertStatus(422);

        $this->deleteJson("/api/admin/rates/{$rate['id']}")->assertNoContent();
        $this->assertSame('200.00', $quote());
    }

    public function test_dashboard_and_calendar_summarise_the_house(): void
    {
        $this->addRooms(2);
        $receptionist = $this->staff('receptionist');

        $this->postJson('/api/bookings', $this->bookingPayload(['check_in' => $this->date(0), 'check_out' => $this->date(2)]))->assertCreated();
        Contact::create(['name' => 'Q', 'email' => 'q@example.com', 'message' => 'Parking?']);

        $this->actingAs($receptionist, 'sanctum')->getJson('/api/admin/dashboard')->assertOk()
            ->assertJsonPath('rooms_sellable', 2)
            ->assertJsonPath('occupancy_today', 50)
            ->assertJsonCount(1, 'arrivals')
            ->assertJsonPath('arrivals.0.guest_name', 'Ana Silva')
            ->assertJsonPath('unread_messages', 1);

        $room = Room::orderBy('id')->skip(1)->first();
        $this->postJson('/api/admin/room-blocks', [
            'room_id' => $room->id, 'start_date' => $this->date(3), 'end_date' => $this->date(4),
        ])->assertCreated()->assertJsonPath('start_date', $this->date(3));

        $this->getJson('/api/admin/calendar?from=' . $this->date(0) . '&days=7')->assertOk()
            ->assertJsonCount(2, 'rooms')
            ->assertJsonCount(1, 'bookings')
            ->assertJsonPath('bookings.0.check_out', $this->date(2))
            ->assertJsonPath('blocks.0.room_id', $room->id);

        // Receptionists can see rooms but not change them, nor manage staff
        $this->getJson('/api/admin/room-types')->assertOk();
        $this->postJson('/api/admin/rooms', ['room_type_id' => $this->roomType->id, 'number' => 'X'])->assertForbidden();
        $this->getJson('/api/admin/staff')->assertForbidden();
    }

    public function test_staff_management_protects_owners(): void
    {
        $owner = $this->staff('owner');
        $admin = $this->staff('admin');

        // Admin cannot create an owner
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/staff', [
            'name' => 'X', 'email' => 'x@hotel.test', 'password' => 'secret123', 'role' => 'owner',
        ])->assertForbidden()->assertJsonPath('code', 'forbidden_role');

        $id = $this->postJson('/api/admin/staff', [
            'name' => 'Rita', 'email' => 'rita@hotel.test', 'password' => 'secret123', 'role' => 'receptionist',
        ])->assertCreated()->assertJsonPath('role', 'receptionist')->json('id');

        $this->postJson('/api/login', ['email' => 'rita@hotel.test', 'password' => 'secret123'])
            ->assertOk()->assertJsonPath('user.roles', ['receptionist']);

        // Admin cannot demote the owner
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/staff/{$owner->id}", ['role' => 'admin'])->assertForbidden();

        // The only owner cannot be demoted, even by themselves
        $this->actingAs($owner, 'sanctum')->patchJson("/api/admin/staff/{$owner->id}", ['role' => 'admin'])
            ->assertStatus(409)->assertJsonPath('code', 'last_owner');
        $this->deleteJson("/api/admin/staff/{$owner->id}")->assertStatus(409);

        $this->deleteJson("/api/admin/staff/{$id}")->assertNoContent();
        $this->assertTrue(User::find($id)->hasRole('guest'));
        $this->getJson('/api/admin/staff')->assertOk()->assertJsonMissing(['email' => 'rita@hotel.test']);
    }

    public function test_contact_inbox_and_faq_admin(): void
    {
        $editor = $this->staff('editor');
        $c = Contact::create(['name' => 'Q', 'email' => 'q@example.com', 'message' => 'Hi']);

        $this->actingAs($editor, 'sanctum')->getJson("/api/admin/contacts/{$c->id}")->assertOk()->assertJsonPath('status', 'read');

        $faq = $this->postJson('/api/admin/faqs', ['question' => 'Pets?', 'answer' => 'Yes', 'active' => false])->assertCreated()->json();
        $this->getJson('/api/admin/faqs')->assertOk()->assertJsonFragment(['question' => 'Pets?']);
        $this->getJson('/api/faqs')->assertOk()->assertJsonMissing(['question' => 'Pets?']);
        $this->deleteJson("/api/admin/faqs/{$faq['id']}")->assertNoContent();
    }
}
