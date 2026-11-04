<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Plans 
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Get started with one app basic features.',
                'price_monthly' => 0.00,
                'price_yearly' => 0.00,
                'max_apps' => 1,
                'max_products' => 10,
                'max_domains' => 1,
                'features' => json_encode([
                    'custom_domain' => false,
                    'analytics' => false,
                    'priority_support' => false,
                    'branding' => true, // Mercatura branding visible
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For small businesses ready to grow.',
                'price_monthly' => 49.90,
                'price_yearly' => 479.00,
                'max_apps' => 3,
                'max_products' => 100,
                'max_domains' => 2,
                'features' => json_encode([
                    'custom_domain' => false,
                    'analytics' => false,
                    'priority_support' => false,
                    'branding' => false,
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Unlimited power for serious businesses.',
                'price_monthly' => 149.90,
                'price_yearly' => 1439.00,
                'max_apps' => 999,
                'max_products' => 999999,
                'max_domains' => 10,
                'features' => json_encode([
                    'custom_domain' => true,
                    'analytics' => true,
                    'priority_support' => true,
                    'branding' => false,
                ]),
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            DB::connection('mercatura')
                ->table('plans')
                ->updateOrInsert(['slug' => $plan['slug']], $plan);
        }

        $this->command->info('Plans seeded');

        // App Types
        $appTypes = [
            [
                'name' => 'E-commerce',
                'slug' => 'ecommerce',
                'description' => 'Full-featured online store with products, cart, checkout and orders.',
                'icon' => 'shopping-cart',
                'db_template' => 'mercatura_tenant_demo',
                'is_active' => true,
            ],
            [
                'name' => 'Restaurant',
                'slug' => 'restaurant',
                'description' => 'Digital menu, table reservations and online ordering.',
                'icon' => 'utensils',
                'db_template' => null, // future
                'is_active' => false,
            ],
            [
                'name' => 'Streamer',
                'slug' => 'streamer',
                'description' => 'Content creator page with subscriptions and digital products.',
                'icon' => 'video',
                'db_template' => null, // future
                'is_active' => false,
            ],
        ];

        foreach ($appTypes as $type) {
            DB::connection('mercatura')
                ->table('app_types')
                ->updateOrInsert(['slug' => $type['slug']], $type);
        }

        $this->command->info('App types seeded');

        // Themes
        $ecommerceTypeId = DB::connection('mercatura')
                ->table('app_types')
                ->where('slug', 'ecommerce')
                ->value('id');

        $themes = [
            [
                'app_type_id' => $ecommerceTypeId,
                'name' => 'Classic',
                'slug' => 'ecommerce-classic',
                'description' => 'Clean and minimal e-commerce theme. Works for any product type.',
                'preview_image' => null,
                'thumbnail' => null,
                'is_active' => true,
                'is_default' => true,
            ],
            [
                'app_type_id' => $ecommerceTypeId,
                'name' => 'Luxury',
                'slug' => 'ecommerce-luxury',
                'description' => 'Elegant dark theme for premium and jewelry brands.',
                'preview_image' => null,
                'thumbnail' => null,
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'app_type_id' => $ecommerceTypeId,
                'name' => 'Bold',
                'slug' => 'ecommerce-bold',
                'description' => 'High-contrast vibrant theme for sports and lifestyle brands.',
                'preview_image' => null,
                'thumbnail' => null,
                'is_active' => true,
                'is_default' => false,
            ],
        ];

        foreach ($themes as $theme) {
            DB::connection('mercatura')
                ->table('themes')
                ->updateOrInsert(['slug' => $theme['slug']], $theme);
        }

        $this->command->info('Themes seeded');

        // Demo tenant
        $adminUser = DB::connection('mercatura')
                ->table('users')
                ->where('email', 'admin@ecommerce.local')
                ->first();

        $freePlanId = DB::connection('mercatura')
                ->table('plans')
                ->where('slug', 'free')
                ->value('id');

        if ($adminUser && $freePlanId) {
            $tenantId = DB::connection('mercatura')
                ->table('tenants')
                ->insertGetId([
                    'user_id' => $adminUser->id,
                    'name' => 'Mercatura Demo',
                    'slug' => 'demo',
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::connection('mercatura')
                ->table('subscriptions')
                ->insert([
                    'tenant_id' => $tenantId,
                    'plan_id' => $freePlanId,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);            
            
            $defaultThemeId = DB::connection('mercatura')
                ->table('themes')
                ->where('slug', 'ecommerce-classic')
                ->value('id');

            $appTypeId = DB::connection('mercatura')
                ->table('app_types')
                ->where('slug', 'ecommerce')
                ->value('id');

            $tenantAppId = DB::connection('mercatura')
                ->table('tenant_apps')
                ->insertGetId([
                    'tenant_id' => $tenantId,
                    'app_type_id' => $appTypeId,
                    'theme_id' => $defaultThemeId,
                    'name' => 'Mercatura Demo Store',
                    'slug' => 'demo-store',
                    'db_name' => 'mercatura_tenant_demo',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::connection('mercatura')
                ->table('domain_mappings')
                ->insert([
                    'tenant_app_id' => $tenantAppId,
                    'domain' => 'demo.mercatura.devitor.local',
                    'type' => 'subdomain',
                    'is_primary' => true,
                    'is_verified' => true,
                    'verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->command->info(' Demo tenant seeded');
        }
    }
}
