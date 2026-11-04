<?php

namespace App\Http\Middleware;

use App\Models\DomainMapping;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant from the incoming request domain.
 * 
 * Flow:
 * 1. Extract host form request
 * 2. Look up domain_mappings table
 * 3. Find the tenant_app and its db_name
 * 4. Switch the tenant DB connection dynamically
 * 5. Store tenant context in the request for controllers
 * 
 * Platform routes (admin., api.) bypass tenant resolution.
 */
class ResolveTenant
{   
    // Domains that belongs to the platform itself - skip tenant resolution
    private array $platformDomains = [
        'mercatura.devitor.local',
        'admin.mercatura.devitor.local',
        'api.mercatura.devitor.local',
        'ecommerce-blog.devitor.local', // legacy dev domain
        'localhost',
        '127.0.0.1',
    ];

    /**
     * Handle an incoming request.
     * 
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // Skip tenant resolution for platform domains
        if (in_array($host, $this->platformDomains)) {
            return $next($request);
        }

        // Look up the domain in domain_mappings
        $domainMapping = DomainMapping::with('tenantApp')
            ->where('domain', $host)
            ->where('is_verified', true)
            ->first();

        if (!$domainMapping) {
            Log::warning("ResolveTenant: unknown domain [{$host}]");
            return response()->json([
                'message' => 'Domain not recognized.',
            ], 404);
        }

        $tenantApp = $domainMapping->tenantApp;

        if (!$tenantApp->isActive()) {
            return response()->json([
                'message' => 'This application is currently unavailable.',
            ], 503);
        }
        
        // Switch tenant DB connection dynamically
        Config::set('database.connections.tenant.database', $tenantApp->db_name);

        // Store tenant context on the request for controllers
        $request->merge([
            '_tenant_app' => $tenantApp,
            '_tenant_db' => $tenantApp->db_name,
            '_tenant_app_id' => $tenantApp->id,
        ]);

        // Also make it available via app() container
        app()->instance('currentTenantApp', $tenantApp);

        Log::info("Tenant resolved: [{$host}] -> [{$tenantApp->db_name}]");
        
        return $next($request);
    }
}
