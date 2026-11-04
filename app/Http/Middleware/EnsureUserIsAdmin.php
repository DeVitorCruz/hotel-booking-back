<?php
 namespace App\Http\Middleware;

 use Closure;
 use Illuminate\Http\Request;
 use Symfony\Component\HttpFoundation\Response;

 /**
  * Middleware to restrict access to admin-only routes.
  * 
  * Verifies that the authenticated user has the 'admin' role
  * assigned via Spatie Laravel Permission. If the check fails,
  * a 403 JSON response is returned immediately.
  * 
  * Usage: apply the 'admin' alias registered in bootstrap/app.php
  * to any route or route group that requires admin access.
  */
 class EnsureUserIsAdmin
 {
	 
	/**
	 * Handle an incomming request.
	 * 
	 * @param Request $request The incoming HTTP request.
	 * @param Closure $next    The next middleware or controller handler.
	 * @return Response 403 JSON if user is not admin, otherwise passes through.
	 */
    public function handle(Request $request, Closure $next): Response
    {
       if (!$request->user() || !$request->user()->hasAnyRole(['admin', 'owner'])) {
           return response()->json([
               'message' => 'Unauthorized. Admin access required.',
           ], 403);
       }

       return $next($request);
   }
 }
