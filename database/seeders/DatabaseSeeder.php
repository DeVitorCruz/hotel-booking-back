<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan migrate --seed
     *   → roles/permissions, the owner account (OWNER_EMAIL / OWNER_PASSWORD),
     *     and demo rooms unless SEED_DEMO_HOTEL=false.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $email = env('OWNER_EMAIL', 'owner@example.com');
        $password = env('OWNER_PASSWORD') ?: Str::password(16);

        $owner = User::firstOrCreate(
            ['email' => $email],
            ['name' => env('OWNER_NAME', 'Hotel Owner'), 'password' => $password, 'email_verified_at' => now()],
        );
        $owner->syncRoles(['owner']);

        if ($owner->wasRecentlyCreated && ! env('OWNER_PASSWORD')) {
            $this->command?->warn("Owner account {$email} created with password: {$password}");
        }

        if (filter_var(env('SEED_DEMO_HOTEL', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(DemoHotelSeeder::class);
        }
    }
}
