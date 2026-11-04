<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Handle user registration
     * 
     * @param Request $request
     * @return JsonResponse 201, user successfully registered
     * @throws \Illuminate\Validation\ValidationException
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|string|email|max:191|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

         // Assign default buyer role on registration
        $user->assignRole('buyer');

        if (config('fortify.features.emailVerification'))
        {
            event(new Registered($user));
        }

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $this->userPlayload($user),
        ], 201);
    }

    /**
     * Authenticate a tenant user and issue a Sanctum Bearer token.
     * Response includes roles + permissions
     * 
     * @param Request $request
     * @return JsonResponse 201, user successfully logged
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.']
            ]);
        }

        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPlayload($user),
            'device_info' => $this->getDeviceInfo($request),
        ]);
    }

    /**
     * Revoke the current Bearer token.
     * 
     * @param Request $request
     * @return JsonResponse 200, user successfully revoked
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Return the authenticated user with roles and permissions.
     *  
     * @param Request $request
     * @return JsonResponse 200, user hydrated returned
     */
    public function me(Request $request): JsonResponse 
    {
        return response()->json($this->userPlayload($request->user())); 
    }

    /**
     * Send a password reset link.
     * 
     * @param Request $request
     * @return JsonResponse 200, reset link successfully sent
     * @throws \Illuminate\Validation\ValidationException
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)], 200);
        }

        throw ValidationException::withMessages(['email' => [__($status)]]);
    }

    /**
     * Reset the given user's password.
     * 
     * @param Request $request
     * @return JsonResponse 200, password successfully updated
     * @throws \Illuminate\Validation\ValidationException
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                ])->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 200);
        }

        throw ValidationException::withMessages(['email' => [__($status)]]);
    }

    // Helpers

    /**
     * Feed all necessary information the user
     * 
     * @param User $user, user whow made the request
     * @return array List with all user attributes 
     */
    private function userPlayload(User $user): array 
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }

    /**
     * Extract basic device metada from the request headers.
     * 
     * @param \Illuminate\Http\Request $request
     * @return array List all basic metadata
     */
    protected function getDeviceInfo(Request $request): array
    {
        $ua = $request->header('User-Agent');
        $platform = null;
        $browser = null;
        $deviceType = 'Unknown';

        if ($ua) {
            if (preg_match('/(msie|firefox|chrome|safari|opera|edge|trident)/i', $ua, $m)) {
                $browser = strtolower($m[1]);
                if (in_array($browser, ['msie', 'trident'])) $browser = 'Internet Explorer';
            }
            if (preg_match('/windows|linux|mac os|android|iphone|ipad/i', $ua, $m)) {
                $platform = strtolower($m[0]);
            }

            $deviceType = preg_match('/mobile|android|iphone|ipad/i', $ua) ? 'Mobile' 
                : (preg_match('/tablet/i', $ua) ? 'Tablet' : 'Desktop');
        }

        return [
            'user_agent' => $ua,
            'ip_address' => $request->ip(),
            'accept_language' => $request->header('Accept-Language'),
            'browser' => $browser,
            'platform' => $platform,
            'device_type' => $deviceType,
        ];
    }
}
