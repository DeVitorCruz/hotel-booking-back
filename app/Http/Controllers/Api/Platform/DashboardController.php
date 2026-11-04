<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant; 
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /platform/dashbloard
     * Returns full dashboard data for the authenticated tenant owner.
     * 
     * @param  Request      $request
     * @return JsonResponse 
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tenant = Tenant::where('user_id', $user->id)
            ->with([
                'subscription.plan',
                'apps.theme',
                'apps.appType',
                'apps.domains',
            ])->first();
        
        if (!$tenant) {
            return response()->json([
                'message' => 'No tenant account found. Please register frist.',    
            ], 404);
        }

        $plan = $tenant->subscription?->plan;
        
        // --- Trial info ----------------------------
        $trialDaysRemaining = null;
        if ($tenant->isTrial() && $tenant->trial_ends_at) {
            $trialDaysRemaining = max(0, (int) now()->diffInDays($tenant->trial_ends_at, false));
        }
        
        // --- Apps with per-app stats ----------------------------
        $apps = $tenant->apps->map(function ($app) {
            $stats = $this->getAppStats($app->db_name);

            return [
                'id' => $app->id,
                'name' => $app->name,
                'slug' => $app->slug,
                'status' => $app->status,
                'app_type' => $app->appType?->name,
                'theme' => $app->theme?->name,
                'primary_domain' => $app->domains
                    ->where('is_primary', true)
                    ->first()?->domain,
                'domains_count' => $app->domains->count(),
                'stats' => $stats,
            ];
        });

        // --- Plan limits usage ----------------------------
        $totalProducts = $tenant->apps->sum(function ($app) {
            return $this->getAppProductCount($app->db_name);
        });

        $totalDomains = $tenant->apps->sum(function ($app) {
            return $app->domains->count();
        });

        // --- Recent activity (from all tenant apps) ----------------------------
        $recentOrders = $this->getRecentOrders($tenant);
        $recentContacts = $this->getRecentContacts($tenant);

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'trial_days_remaining' => $trialDaysRemaining,
                'trial_ends_at' => $tenant->trial_ends_at,
            ],
            'subscription' => [
                'plan' => $plan->name,
                'plan_slug' => $plan->slug,
                'status' => $tenant->subscription?->status,
                'current_period_end' => $tenant->subscription?->current_period_end,
                'price_monthly' => $plan?->price_monthly,
            ],
            'limits' => [
                'apps' => [
                    'used' => $tenant->apps->count(),
                    'max' => $plan?->max_apps ?? 1,
                ],
                'products' => [
                    'used' => $totalProducts,
                    'max' => $plan?->max_products ?? 10,
                ],
                'domains' => [
                    'used' => $totalDomains,
                    'max' => $plan?->max_domains ?? 1,
                ],
            ],
            'apps' => $apps,
            'recent_orders' => $recentOrders,
            'recent_contacts' => $recentContacts,
        ]);
    }

    /**
     * GET /platform/dashboard/app/{tenantApp}
     * Detailed stats for a single app.
     * 
     * @param  Request $request
     * @param  \App\Models\TenantApp $tenantApp
     * @return JsonResponse
     */
    public function app(Request $request, \App\Models\TenantApp $tenantApp): JsonResponse 
    {
        $tenant = Tenant::where('user_id', $request->user()->id)->firstOrFail();

        if ($tenantApp->tenant_id !== $tenant->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $stats = $this->getAppStats($tenantApp->db_name);

        return response()->json([
            'app' =>  $tenantApp->load(['appType', 'theme', 'domains']),
            'stats' => $stats,
        ]);
    }

    // --- Private helpers ----------------------------
    /**
     * Get apps stats
     * 
     * @param  string $dbName
     * @return array
     */
    private function getAppStats(string $dbName): array 
    {
        try {
            Config::set('database.connections.tenant.database', $dbName);
            DB::purge('tenant');

            return [
                'products' => DB::connection('tenant')->table('products')->count(),
                'orders' => DB::connection('tenant')->table('orders')->count(),
                'orders_pending' => DB::connection('tenant')->table('orders')->where('status', 'pending')->count(),
                'orders_paid' => DB::connection('tenant')->table('orders')->where('status', 'paid')->count(),
                'revenue' => DB::connection('tenant')->table('payments')->where('status', 'paid')->sum('amount'),
                'contacts_unread' => DB::connection('tenant')->table('contacts')->where('status', 'unread')->count(),
                'reviews_pending' => DB::connection('tenant')->table('reviews')->where('status', 'pending')->count(),
            ];
        } catch (\Exception $e) {
            return [
                'products' => 0,
                'orders' => 0,
                'orders_pending' => 0,
                'orders_paid' => 0,
                'revenue' => 0,
                'contacts_unread' => 0,
                'reviews_pending' => 0,
            ];
        }
    }

    /**
     * Get recent orders
     * 
     * @param  Tenant $tenant
     * @return array
     */
    private function getRecentOrders(Tenant $tenant): array 
    {
        $orders = [];

        foreach ($tenant->apps as $app) {
            try {
                Config::set('database.connections.tenant.database', $app->db_name);
                DB::purge('tenant');

                $appOrders = DB::connection('tenant')
                    ->table('orders')
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get()
                    ->map(fn($o) => [
                        'id' => $o->id,
                        'status' => $o->status,
                        'total_amount' => $o->total_amount,
                        'created_at' => $o->created_at,
                        'app_name' => $app->name,
                    ]);
                
                $orders = array_merge($orders, $appOrders->toArray());
            } catch (\Exception $e) {
                continue;
            }
        }

        // Sort by created_at desc, return latest 10
        usort($orders, fn($a, $b) => $b['created_at'] <=> $a['created_at']);
        return array_slice($orders, 0, 10);
    }

    /**
     * Get Recent Contacts
     * 
     * @param  Tenant $tenant
     * @return array 
     */
    private function getRecentContacts(Tenant $tenant): array
    {
        $contacts = [];

        foreach($tenant->apps as $app) {
            try {
                Config::set('database.connections.tenant.database', $app->db_name);
                DB::purge('tenant');

                $appContects = DB::connection('tenant')
                    ->table('contacts')
                    ->where('status', 'unread')
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get()
                    ->map(fn($c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'subject' => $c->subject,
                        'status' => $c->status,
                        'created_at' => $c->created_at,
                        'app_name' => $app->name,
                    ]);
                
                $contacts = array_merge($contacts, $appContects->toArray());
            } catch (\Exception $e) {
                continue;
            }
        }
        usort($contacts, fn($a, $b) => $b['created_at'] <=> $a['created_at']);
        return array_slice($contacts, 0, 10);
    }

    /**
     * Get app Product counts
     * 
     * @param  string $dbName
     * @return int
     */
    private function getAppProductCount(string $dbName): int 
    {
        try {
            Config::set('database.connections.tenant.database', $dbName);
            DB::purge('tenant');
            return DB::connection('tenant')->table('products')->count();
        } catch (\Exception $e) {
            return 0; 
        }
    }
}
