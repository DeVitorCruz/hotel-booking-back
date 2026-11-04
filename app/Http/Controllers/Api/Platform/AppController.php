<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Services\Tenant\TenantProvisioningService;
use App\Models\AppType;
use App\Models\DomainMapping;
use App\Models\Tenant;
use App\Models\TenantApp;
use App\Models\Theme;
use App\Models\PlatformUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class AppController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $provisioning,
    ) {}

    /**
     * GET /platform/apps
     * List tenant's apps.
     * 
     * @param  Request      $request, user request apps list
     * @return JsonResponse 200, list apps
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = Tenant::where('user_id', $request->user()->id)->firstOrFail();

        return response()->json(
            $tenant->apps()->with(['appType', 'theme', 'domains'])->get()
        );
    }

    /**
     * POST /platform/apps
     * Create a new app for the tenant.
     * 
     * @param  Request      $request, user request to create app
     * @return JsonResponse 201, app successfully created
     */
    public function store(Request $request): JsonResponse 
    {
        /** @var PlatformUser $platformUser */
        $platformUser = $request->user();

        $tenant = Tenant::where('user_id', $platformUser->id)
            ->with('subscription.plan')
            ->firstOrFail();
        
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'app_type_slug' => 'required|exists:app_types,slug',
            'theme_slug' => 'nullable|exists:themes,slug',
        ]);

        // Check plan limits
        $plan = $tenant->subscription->plan;
        $appCount = $tenant->apps()->count();

        if ($appCount >= $plan->max_apps) {
            return response()->json([
                'message' => "Your plan allows max {$plan->max_apps} app(s). Upgrade to create more.",
            ], 403);
        }

        // resolve app type and theme
        $appType = AppType::where('slug', $data['app_type_slug'])
            ->where('is_active', true)
            ->firstOrFail();
        
        // Get theme - defualt if not specified
        $theme = ($data['theme_slug'] ?? null)
            ? Theme::where('slug', $data['theme_slug'])
                ->where('app_type_id', $appType->id)
                ->firstOrFail()
            : Theme::where('app_type_id', $appType->id)
                ->where('is_default', true)
                ->firstOrFail();

        // create the TenantApp record
        $dbName = 'mercatura_tenant_' . $tenant->id . '_' . Str::random(6);
        $slug = Str::slug($data['name']) . '_' . Str::random(4);

        $tenantApp = TenantApp::create([
            'tenant_id' => $tenant->id,
            'app_type_id' => $appType->id,
            'theme_id' => $theme->id,   
            'name' => $data['name'],
            'slug' => $slug,
            'db_name' => $dbName,
            'status' => 'provisioning',
        ]);

        // Auto-create subdomain
        $subdomain = $tenant->slug . '-' . $slug . '.mercatura.devitor.local';
        DomainMapping::create([
            'tenant_app_id' => $tenantApp->id,
            'domain' => $subdomain,
            'type' => 'subdomain',
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        // Provision tenant DB
        // TenantProvisioningService handles:
        // CREATE DATABASE -> migrate:tenant -> seed roles -> owner user -> active
        // On failure tha app is marked 'failed' and the exception re-thrown.
        $this->provisioning->provision($tenantApp, $platformUser);

        return response()->json([
            'message' => 'App created and provisioned.',
            'app' => $tenantApp->fresh()->load(['appType', 'theme', 'domains']),
            'subdomain' => $subdomain,
        ], 201);
    }

    /**
     * PATCH /platform/apps/{tenantApp}/theme
     * Switch theme for an existing app.
     * 
     * @param  Request      $request
     * @param  TenantApp    $tenantApp
     * @return JsonResponse 200, theme successfully switched
     *                      403, unauthorized.
     */
    public function switchTheme(Request $request, TenantApp $tenantApp): JsonResponse
    {
        $tenant = Tenant::where('user_id', $request->user()->id)->firstOrFail();

        if ($tenantApp->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'theme_slug' => 'required|exists:themes,slug',
        ]);

        $theme = Theme::where('slug', $data['theme_slug'])
            ->where('app_type_id', $tenantApp->app_type_id)
            ->firstOrFail();

        $tenantApp->update(['theme_id' => $theme->id]);

        return response()->json([
            'message' => 'Theme switched successfully.',
            'app' => $tenantApp->fresh()->load(['appType', 'theme']),
        ]);
    }
}
