<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hotel roles and permissions (guard: web).
 *
 * owner        - Full control, including settings and staff
 * admin        - Everything except changing the owner
 * receptionist - Bookings, check-in/out, blocked dates, view rooms
 * editor       - Blog, FAQ, contacts, staff spotlights
 * guest        - Own bookings and profile
 *
 * Run standalone: php artisan db:seed --class=RolesAndPermissionsSeeder
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Hotel setup
            'settings.manage',
            'staff.manage',

            // Rooms & rates
            'rooms.view',
            'rooms.manage',
            'rates.manage',
            'room-blocks.manage',

            // Bookings
            'bookings.view-own',
            'bookings.cancel-own',
            'bookings.view-any',
            'bookings.manage',      // create manual bookings, change status, check-in/out
            'bookings.cancel-any',
            'payments.view',

            // Profiles
            'profiles.view',
            'profiles.edit-own',

            // Content
            'blog.view',
            'blog.create',
            'blog.edit-own',
            'blog.edit-any',
            'blog.delete-own',
            'blog.delete-any',
            'blog.publish',
            'faq.manage',
            'spotlights.manage',

            // Contacts
            'contacts.submit',
            'contacts.view-own',
            'contacts.view-any',
            'contacts.reply',
            'contacts.delete',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $all = Permission::where('guard_name', 'web')->pluck('name')->toArray();

        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web'])->syncPermissions($all);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions($all);

        Role::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web'])->syncPermissions([
            'rooms.view', 'room-blocks.manage',
            'bookings.view-any', 'bookings.manage', 'bookings.cancel-any', 'payments.view',
            'profiles.view', 'profiles.edit-own',
            'contacts.view-any', 'contacts.reply',
        ]);

        Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web'])->syncPermissions([
            'rooms.view',
            'profiles.view', 'profiles.edit-own',
            'blog.view', 'blog.create', 'blog.edit-own', 'blog.delete-own', 'blog.publish',
            'faq.manage', 'spotlights.manage',
            'contacts.view-any', 'contacts.reply',
        ]);

        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web'])->syncPermissions([
            'bookings.view-own', 'bookings.cancel-own',
            'profiles.view', 'profiles.edit-own',
            'blog.view',
            'contacts.submit', 'contacts.view-own',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
