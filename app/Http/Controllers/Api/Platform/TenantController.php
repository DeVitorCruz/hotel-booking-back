<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * GET /platform/tenant
     * Get authenticated user's tenant.
     * 
     * @param  Request      $request
     * @return JsonResponse 200, Tenant proprieties.
     *                      404, Tenant not found
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = Tenant::where('user_id', $request->user()->id)
            ->with(['subscription.plan', 'apps.appType', 'apps.theme', 'apps.domains'])
            ->first();

        if (!$tenant) {
            return response()->json(['message' => 'No tenant found.'], 404);
        }

        return response()->json($tenant);
    }

    /**
     * POST /platform/tenant
     * Register a new tenant - self-service.
     * 
     * @param  Request      $request
     * @return JsonResponse 201, Tenant successfully created.
     *                      409, Your already have a Tenant account.
     */     
    public function store(Request $request): JsonResponse 
    {
        $user = $request->user();

        // One tenant per user
        if (Tenant::where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You already have a tenant account.',
            ], 409);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:50|unique:tenants,slug|alpha_dash',
            'plan_slug' => 'nullable|string|exists:plans,slug',
        ]);

        $plan = Plan::where('slug', $data['plan_slug'] ?? 'free')
            ->where('is_active', true)
            ->firstOrFail();
        
        $tenant = Tenant::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['slug']),
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);

        return response()->json([
            'message' => 'Tenant created. You have 14 days free trial.',
            'tenant' => $tenant->load('subscription.plan'),
        ], 201);
    }

    /**
     * PATCH /platform/tenant
     * Update tenant name.
     * 
     * @param  Request      $request, user request to update the Tenant
     * @return JsonResponse 200, name successfully updated.
     */
    public function update(Request $request): JsonResponse
    {
        $tenant = Tenant::where('user_id', $request->user()->id)->firstOrFail();

        $tenant->update($request->validate([
            'name' => 'required|string|max:100',
        ]));

        return response()->json($tenant->fresh());
    }
}
