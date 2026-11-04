<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\PlatformUser;

/**
 * Seeds platform-level roles and permissions into the mercatura DB.
 * 
 * Guard: platform
 * DB: mercatura (set as default connection during this seeder)
 * 
 * Roles
 * 
 * owner - Total platform control (all permissions)
 * 
 * admin - Operational management (tenants, plans, billing, apps, subscriptions, content)
 * 
 * editor - Platform content (blog, faq, contacts reply)
 * 
 * support - Client support (tenants.view, contacts, subscriptions.view)
 * 
 * tenant - Platform client (self-service: own apps, subscription, billing)
 * 
 * Run standalone:
 *   php artisan db:seed --class=PlatformRolesAndPermissionsSeeder
 */
class PlatformRolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $previous = config('database.default');
        config(['database.default' => 'mercatura']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // PERMISSIONS

        $permissions = [

            // Tenants
            'tenants.view', // list / read any tenant

            'tenants.create',
            'tenants.edit',
            'tenants.delete',
            'tenants.suspend',
            'tenants.view-own', // tenant role: see own tenant record

            // Plans
            'plans.view',
            'plans.create',
            'plans.edit',
            'plans.delete',

            // Billing
            'billing.view', // view any tenant's billing
            'billing.manage', // manage any tenant's billing
            'billing.view-own', // tenant role: view own invoices / payment
            'billing.manage-own', // tenant role: update own payment method

            // Platform users (Mercatura staff)
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.assign-roles',

            // App catalog (the types of apps Mercatura offers)
            'apps.catalog.view',
            'apps.catalog.create',
            'apps.catalog.edit',
            'apps.catalog.delete',

            // Tenant apps (apps a tenant subscribes to / owns)
            'apps.manage-own',

            // Subscriptions
            'subscriptions.view', // view any subscription
            'subscriptions.manage', // create / cancel any subscription
            'subscriptions.view-own', // tenant role: view own subscription

            // Platform blog
            'platform.blog.view',
            'platform.blog.create',
            'platform.blog.edit-own',
            'platform.blog.edit-any',
            'platform.blog.delete-own',
            'platform.blog.delete-any',
            'platform.blog.publish',
            
            // Platform FAQ
            'platform.faq.view',
            'platform.faq.manage',

            // Platform contacts (support inbox)
            'platform.contacts.submit',
            'platform.contacts.view-own',
            'platform.contacts.view-any',
            'platform.contacts.reply',
            'platform.contacts.delete',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'platform',
            ]);
        }

        $this->command->info(count($permissions) . ' platform permissions seeded.');

        // ROLES

        // OWNER - total platform control
        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'platform']);
        $owner->syncPermissions(
            Permission::where('guard_name', 'platform')
                ->pluck('name')->toArray()
        );

        // ADMIN - operational management
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'platform']);
        $admin->syncPermissions([
            'tenants.view', 'tenants.create', 'tenants.edit',
            'tenants.suspend', 'plans.view', 'plans.create', 'plans.edit',
            'billing.view', 'billing.manage', 'users.view', 'users.create',
            'users.edit', 'users.assign-roles', 'apps.catalog.view', 'apps.catalog.create',
            'apps.catalog.edit', 'subscriptions.view', 'subscriptions.manage',
            'platform.blog.view', 'platform.blog.create', 'platform.blog.edit-own',
            'platform.blog.edit-any', 'platform.blog.delete-own', 'platform.blog.publish',
            'platform.faq.view', 'platform.faq.manage', 'platform.contacts.view-any', 'platform.contacts.reply',
            'platform.contacts.delete',
        ]);

        // EDITOR - platform content only
        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'platform']);
        $editor->syncPermissions([
            'platform.blog.view', 'platform.blog.create', 'platform.blog.edit-own',
            'platform.blog.delete-own', 'platform.blog.publish', 'platform.faq.view',
            'platform.faq.manage', 'platform.contacts.view-any', 'platform.contacts.reply',
        ]);

        // SUPPORT - client-facing support
        $support = Role::firstOrCreate(['name' => 'support', 'guard_name' => 'platform']);
        $support->syncPermissions([
            'tenants.view',
            'subscriptions.view',
            'platform.contacts.view-any', 'platform.contacts.reply'
        ]);

        // TENANT - platform client (self-service only)
        $tenant = Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'platform']);
        $tenant->syncPermissions([
            'tenants.view-own',
            'apps.manage-own',
            'subscriptions.view-own',
            'billing.view-own', 'billing.manage-own',
            'platform.contacts.submit', 'platform.contacts.view-own',
        ]);

        $this->command->info(
            Role::where('guard_name', 'platform')
                ->count() . ' platform roles seeded.'
        );

        // PLATFORM OWNER USER
        $ownerUser = PlatformUser::firstOrCreate(
            ['email' => 'owner@mercatura.local'],
            [
                'name' => 'Mercatura Owner',
                'password' => Hash::make('changeme123'),
            ],
        );

        if (!$ownerUser->hasRole('owner', 'platform')) {
            $ownerUser->assignRole($owner);
        }

        $this->command->info('Platform owner -> owner@mercatura.local (password: changeme123)');

        // Restore previous default connection
        config(['database.default' => $previous]);
        app()[PermissionRegistrar::class]
            ->forgetCachedPermissions();
    }
}
