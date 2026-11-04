<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\FAQ;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo content so a fresh install shows a working site.
 * Replace per client (or edit in the back office).
 */
class DemoHotelSeeder extends Seeder
{
    public function run(): void
    {
        $amenities = collect([
            ['Free Wi-Fi', 'wifi'], ['Air conditioning', 'ac_unit'], ['Breakfast included', 'free_breakfast'],
            ['Private bathroom', 'bathtub'], ['Smart TV', 'tv'], ['Sea view', 'water'],
            ['Balcony', 'balcony'], ['Mini bar', 'local_bar'], ['Workspace', 'desk'],
        ])->mapWithKeys(fn ($a) => [Str::slug($a[0]) => Amenity::firstOrCreate(
            ['slug' => Str::slug($a[0])], ['name' => $a[0], 'icon' => $a[1]]
        )]);

        $types = [
            [
                'name' => 'Standard Double', 'beds' => '1 Queen', 'size_sqm' => 18, 'base_price' => 89,
                'max_adults' => 2, 'max_children' => 1, 'max_occupancy' => 3, 'rooms' => ['101', '102', '103', '104'],
                'short' => 'Cozy room with everything you need for a restful night.',
                'amenities' => ['free-wi-fi', 'air-conditioning', 'private-bathroom', 'smart-tv'],
                'image' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=1200',
            ],
            [
                'name' => 'Deluxe King', 'beds' => '1 King', 'size_sqm' => 26, 'base_price' => 129,
                'max_adults' => 2, 'max_children' => 2, 'max_occupancy' => 3, 'rooms' => ['201', '202', '203'],
                'short' => 'Spacious room with a king bed, balcony and breakfast.',
                'amenities' => ['free-wi-fi', 'air-conditioning', 'private-bathroom', 'smart-tv', 'balcony', 'breakfast-included', 'mini-bar'],
                'image' => 'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=1200',
            ],
            [
                'name' => 'Family Suite', 'beds' => '1 King + 2 Twin', 'size_sqm' => 42, 'base_price' => 199,
                'max_adults' => 4, 'max_children' => 3, 'max_occupancy' => 5, 'rooms' => ['301', '302'],
                'short' => 'Two connected rooms with sea view, ideal for families.',
                'amenities' => ['free-wi-fi', 'air-conditioning', 'private-bathroom', 'smart-tv', 'sea-view', 'breakfast-included', 'workspace'],
                'image' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=1200',
            ],
        ];

        foreach ($types as $i => $t) {
            $type = RoomType::updateOrCreate(['slug' => Str::slug($t['name'])], [
                'name' => $t['name'],
                'short_description' => $t['short'],
                'description' => $t['short'] . ' Daily housekeeping, fresh linen and premium toiletries included.',
                'beds' => $t['beds'],
                'size_sqm' => $t['size_sqm'],
                'base_price' => $t['base_price'],
                'max_adults' => $t['max_adults'],
                'max_children' => $t['max_children'],
                'max_occupancy' => $t['max_occupancy'],
                'sort_order' => $i,
                'is_active' => true,
            ]);

            $type->amenities()->sync($amenities->only($t['amenities'])->pluck('id'));
            $type->images()->firstOrCreate(['path' => $t['image']], ['alt' => $t['name'], 'sort_order' => 0]);

            foreach ($t['rooms'] as $number) {
                Room::firstOrCreate(['number' => $number], [
                    'room_type_id' => $type->id,
                    'floor' => substr($number, 0, 1),
                ]);
            }
        }

        foreach ([
            ['What time is check-in and check-out?', 'Check-in is from ' . config('hotel.check_in_time') . ' and check-out is until ' . config('hotel.check_out_time') . '.'],
            ['Can I cancel my booking?', 'Yes. Cancellations up to ' . config('hotel.cancellation_hours') . ' hours before check-in are free and refunded automatically.'],
            ['Which payment methods do you accept?', 'You can pay online with PayPal (account or card) or choose to pay at the front desk.'],
        ] as $i => [$q, $a]) {
            FAQ::firstOrCreate(['question' => $q], ['answer' => $a, 'category' => 'Booking', 'order' => $i, 'active' => true]);
        }
    }
}
