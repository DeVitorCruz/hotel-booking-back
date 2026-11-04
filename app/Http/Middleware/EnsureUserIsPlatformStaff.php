<?php

namespace App\Http\Middleware;

use App\Models\PlatformUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsPlatformStaff
{
    private const PLATFORM_ROLES = ['owner', 'admin', 'editor', 'support', 'tenant'];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof PlatformUser) {
            return response()->json([
                'message' => 'Unauthorized. Platform credentials required.',
            ], 403);
        }

        if (!$user->hasAnyRole(self::PLATFORM_ROLES, 'platform')) {
            return response()->json([
                'message' => 'Unauthorized. No platform role assigned.',
            ], 403);
        }

        return $next($request);
    }
}
