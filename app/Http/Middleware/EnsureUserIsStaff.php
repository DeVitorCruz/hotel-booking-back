<?php
 namespace App\Http\Middleware;

 use Closure;
 use Illuminate\Http\Request;
 use Symfony\Component\HttpFoundation\Response;

 /**
  * Middleware to restrict access to back-office (staff) routes.
  * 
  * Verifies that the authenticated user has a staff role (owner, admin, receptionist, editor)
  * assigned via Spatie Laravel Permission. If the check fails,
  * a 403 JSON response is returned immediately.
  * 
  * Usage: apply the 'admin' alias registered in bootstrap/app.php
  * to any route or route group that requires admin access.
  */
 class EnsureUserIsStaff
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
       if (!$request->user() || !$request->user()->hasAnyRole(\App\Models\User::STAFF_ROLES)) {
           return response()->json([
               'message' => 'Unauthorized. Staff access required.',
           ], 403);
       }

       return $next($request);
   }
 }
