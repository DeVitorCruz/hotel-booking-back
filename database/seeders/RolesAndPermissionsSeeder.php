<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;

/**
 * Seeds tenant workspace and e-commerce app roles/permissions
 * 
 * Guard: web
 * DB: tenant connection (mercatura_tenant_demo by default, driven by TENANT_DB env)
 * 
 * -- Workspace roles (mange the tenant workspace itself) ------------------
 * tenant-owner - Full workspace control (apps, employees, billing)
 * tenant-admin - Manage apps, invite employees, view billing
 * tenant-editor - Tenant-wide content (announcements, docs)
 * 
 * -- E-commerce app roles (seeded at app creation; included here for dev) -
 * store-admin - Full store control
 * seller - Own producs, orders, hire store employees
 * store-employee - Hired by seller; limited store access
 * app-editor - Blog, FAQ, contacts for the app
 * buyer - Cart, wishlist, orders
 * 
 * Run standalone:
 *  php artisan db:seed --class=RolesAndPermissionsSeeder
 * 
 * To target a specific tenant DB at runtime:
 *   TENANT_DB=mercatura_tenant_acme php artisan db:seed --class=RolesAndPermissionsSeeder
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // --- Switch to tenant DB for the duration of this seeder ------
        $previous = config('database.default');
        config(['database.default' => 'tenant']);

        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // - PERMISSIONS
        $permissions = [
            // Workspace
            'workspace.manage', // invite / remove members, menage settings
            'workspace.billing.view', // view subscription and billing info
            'workspace.apps.manage', // create / configure apps within workspace

            // Profiles
            'profiles.view',
            'profiles.edit-own',
            'profiles.edit-any',

            // Employment
            'employment.view',
            'employment.manage-own-store', // seller hires / fires store employees 
            
            // Sellers
            'sellers.view',
            'sellers.apply', // apply to become a seller 
            'sellers.approve', // approve/reject applications
            'sellers.edit-own',
            'sellers.edit-any',
            'sellers.suspend',

            // Products
            'products.view',
            'products.create',
            'products.edit-own',
            'products.edit-any',
            'products.delete-own',
            'products.delete-any',

            // Categories
            'categories.view',
            'categories.manage', // create / edit / delete
            'categories.suggest', // suggest a new category (app-editor)

            // Orders
            'orders.view-own',
            'orders.view-any',
            'orders.update-status',
            'orders.cancel-own', 
            'orders.cancel-any',

            // Cart
            'cart.manage',

            // Wishlist
            'wishlist.manage',

            // Reviews
            'reviews.create',
            'reviews.approve',
 
            // Blog
            'blog.view',
            'blog.create',
            'blog.edit-own',
            'blog.edit-any',
            'blog.delete-own',
            'blog.delete-any',
            'blog.publish',

            // FAQ
            'faq.view',
            'faq.manage',

            // Contacts
            'contacts.submit',
            'contacts.view-own',
            'contacts.view-any',
            'contacts.reply',
            'contacts.delete',
       ];
 
       foreach ($permissions as $perm) {
           Permission::firstOrCreate([
               'name' => $perm,
               'guard_name' => 'web',
           ]);
       }

       $this->command->info(count($permissions) . ' tenant permissions seeded.');

       // WORKSPACE ROLES

       // TENANT-OWNER - full workspace control
       $tenantOwner = Role::firstOrCreate(['name' => 'tenant-owner', 'guard_name' => 'web']);
       $tenantOwner->syncPermissions(
            Permission::where('guard_name', 'web')
                ->pluck('name')->toArray()
       );

       // TENANT-ADMIN - manage apps and team, view billing
       $tenantAdmin = Role::firstOrCreate(['name' => 'tenant-admin', 'guard_name' => 'web']);
       $tenantAdmin->syncPermissions([
            'workspace.apps.manage', 'workspace.billing.view', 
            'profiles.view', 'profiles.edit-own', 
            'sellers.view', 'sellers.approve', 'sellers.suspend', 
            'products.view', 'products.edit-any', 'products.delete-any', 
            'categories.view', 'categories.manage',
            'orders.view-any', 'orders.update-status', 'orders.cancel-any',
            'employment.view', 
            'reviews.approve', 
            'blog.view', 'blog.create', 'blog.edit-any', 'blog.publish',
            'faq.view', 'faq.manage', 
            'contacts.view-any', 'contacts.reply', 'contacts.delete',
       ]);

       // TENANT-EDITOR - tenant-wide content management
       $tenantEditor = Role::firstOrCreate(['name' => 'tenant-editor', 'guard_name' => 'web']);
       $tenantEditor->syncPermissions([
           'profiles.view', 'profiles.edit-own', 
           'products.view', 
           'categories.view', 'categories.suggest', 
           'blog.view', 'blog.create', 'blog.edit-own', 'blog.delete-own', 'blog.publish', 
           'faq.view', 'faq.manage',
           'contacts.view-any', 'contacts.reply', 
       ]);

       // STORE-ADMIN - full store control within the app 
       $storeAdmin = Role::firstOrCreate(['name' => 'store-admin', 'guard_name' => 'web']);
       $storeAdmin->syncPermissions([
           'profiles.view', 'profiles.edit-own',
           'sellers.view', 'sellers.approve', 'sellers.edit-any', 'sellers.suspend',
           'products.view', 'products.create', 'products.edit-any', 'products.delete-any',
           'categories.view', 'categories.manage',
           'orders.view-any', 'orders.update-status', 'orders.cancel-any',
           'employment.view',
           'reviews.approve',
           'blog.view', 'blog.create', 'blog.edit-any', 'blog.publish',
           'faq.view', 'faq.manage',
           'contacts.view-any', 'contacts.reply', 'contacts.delete',
       ]);

       // SELLER - owns a store within the app
       $seller = Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
       $seller->syncPermissions([
           'profiles.view', 'profiles.edit-own',
           'products.view', 'products.create', 'products.edit-own', 'products.delete-own',
           'categories.view', 'categories.suggest',
           'orders.view-own', 'orders.view-any', 'orders.update-status', 'orders.cancel-own',
           'sellers.edit-own',
           'employment.view', 'employment.manage-own-store',
           'blog.view',
           'faq.view',
           'contacts.submit', 'contacts.view-own',
       ]);

       // STORE-EMPLOYEE - hired by a seller
       $storeEmployee = Role::firstOrCreate(['name' => 'store-employee', 'guard_name' => 'web']);
       $storeEmployee->syncPermissions([
           'profiles.view', 'profiles.edit-own',
           'products.view', 'products.create', 'products.edit-own',
           'categories.view',
           'orders.view-any', 'orders.update-status',
           'blog.view',
           'faq.view',
           'contacts.submit', 'contacts.view-any',
       ]);

       // APP-EDITOR - content management within the app
       $appEditor = Role::firstOrCreate(['name' => 'app-editor', 'guard_name' => 'web']);
       $appEditor->syncPermissions([
            'profiles.view', 'profiles.edit-own',
            'products.view',
            'categories.view', 'categories.suggest',
            'blog.view', 'blog.create', 'blog.edit-own', 'blog.delete-own', 'blog.publish',
            'faq.view', 'faq.manage',
            'contacts.view-any', 'contacts.reply',
       ]);

       // BUYER - and customer
       $buyer = Role::firstOrCreate(['name' => 'buyer', 'guard_name' => 'web']);
       $buyer->syncPermissions([
            'profiles.view', 'profiles.edit-own',
            'products.view',
            'categories.view',
            'orders.view-own', 'orders.cancel-own',
            'cart.manage',
            'wishlist.manage',
            'sellers.apply',
            'blog.view',
            'faq.view',
            'contacts.submit', 'contacts.view-own',
       ]);

        $this->command->info(Role::where('guard_name', 'web')->count() . ' tenant roles seeded.');

       // - Restore previous default connection
        config(['database.default' => $previous]);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
   }
}
