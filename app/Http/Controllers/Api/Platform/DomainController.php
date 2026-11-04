<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\DomainMapping;
use App\Models\Tenant;
use App\Models\TenantApp;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DomainController extends Controller
{
    /**
     * POST /platform/apps/{tenantApp}/domains
     * Add a custom domain to an app.
     * 
     * @param  Request      $request
     * @param  TenantApp    $tenantApp
     * @return JsonResponse
     */
    public function store(Request $request, TenantApp $tenantApp): JsonResponse
    {   
        $tenant = Tenant::where('user_id', $request->user()->id)
            ->with('subscription.plan')
            ->firstOrFail();

        if ($tenantApp->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Unauthorized.',], 403);
        }

        // Check plan allows custom domains
        if (!($tenant->subscription->plan->features['custom_domain'] ?? false)) {
            return response()->json([
                'message' => 'Custom domains required a paid plan. Please upgrade.'
            ], 403);
        }
        
        // Check domain limit
        $domainCount = $tenantApp->domains()->count();

        if ($domainCount >= $tenant->subscription->plan->max_domains) {
            return response()->json([
                'message' => 'Domain limit reached for your plan.',
            ], 403);
        }

        $data = $request->validate([
            'domain' => 'required|string|unique:domain_mappings,domain',
        ]);

        $domain = DomainMapping::create([
            'tenant_app_id' => $tenantApp->id,
            'domain' => strtolower($data['domain']),
            'type' => 'custom',
            'is_primary' => false,
            'is_verified' => false,
        ]);

        return response()->json([
            'message' => 'Domain added. Please verify ownership by adding a CNAME record.',
            'domain' => $domain,
            'instructions' => [
                'type' => 'CNAME',
                'name' => $data['domain'],
                'value' => 'mercatura.devitor.local',
            ],
        ], 201);
    }

    /**
     * PATCH /platform/apps/{tenantApp}/domains/{domain}/verify
     * Verify domain ownership (stub - real DNS check later).
     * 
     * @param  Request       $request
     * @param  TenantApp     $tenantApp
     * @param  DomainMapping $domain
     * @return JsonResponse
     */
    public function verify(Request $request, TenantApp $tenantApp, DomainMapping $domain): JsonResponse 
    {   
        $tenant = Tenant::where('user_id', $request->user()->id)->firstOrFail();

        if ($tenantApp->tenant_id !== $tenant->id) {
            return response()-json(['message' => 'Unauthorize.',], 403);
        }

        // TODO: Real DNS CNAME verification
        $domain->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Domain verified successfully.',
            'domain' => $domain->fresh(),
        ]);
    }

    /**
     * DELETE /platform/apps/{tenantApp}/domains/{domain}
     * Remove a custom domain.
     * 
     * @param  Request       $request
     * @param  TenantApp     $tenantApp
     * @param  DomainMapping $domain
     * @return JsonResponse
     */
    public function destroy(Request $request, TenantApp $tenantApp, DomainMapping $domain): JsonResponse 
    {
        $tenant = Tenant::where('user_id', $request->user()->id)->firstOrFail();

        if ($tenantApp->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($domain->isSubdomain()) {
            return response()->json(['message' => 'Subdomain cannot be removed.',], 403);
        }

        $domain->delete();
        
        return response()->json([]);
    }
}
