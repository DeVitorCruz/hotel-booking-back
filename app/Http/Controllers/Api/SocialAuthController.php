<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Contracts\User as SocialUser;

class SocialAuthController extends Controller
{
    private array $supportedProviders = ['google', 'facebook'];

    /**
     * GET /auth/social/{provider}
     * Redirect to provider OAuth page.
     * 
     * @param  string       $provider suppoted provider
     * @return JsonResponse 200, ok
     *                      422, unprocessable entity (provider isn't supported.)
     */
    public function redirect(string $provider): JsonResponse
    {
        if (!in_array($provider, $this->supportedProviders)) {
            return response()->json([
                'message' => "Provider [{$provider}] is not supported.",
            ], 422);
        }

        return response()->json([
            'url' => Socialite::driver($provider)->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * GET /auth/social/{provider}/callback
     * Handle provider callback - find or create user, return Sanctum token.
     *
     * @param  string        $provider suppoted provider 
     * @return JsonResponses 200, ok
     *                       422, unprocessable entity (provider isn't supported or fail to log.)
     */
    public function callback(string $provider): JsonResponses 
    {
        if (!in_array($provider, $this->supportedProviders)) {
            return response()->json([
                'message' => "Provider [{$provider}] is not supported",
            ], 422);
        }

        try {
            $socialUser = Socialite::driver($provider)->staeteless()->user();
        } catch(\Exception $e) {
            Log::error("Social login failed [{$provider}]", ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Social login failed. Please try again.'
            ], 422);
        }

        // Find existing user by provider ID or email
        $user = $this->findOrCreateUser($provider, $socialUser);

        // Create Sanctum token
        $token = $user->createToken('social_auth_' . $provider)->plainTextToken;

        return respones()->json([
            'message' => 'Logged in successfully via ' . ucfirst($provider) . '.',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'roles' => $user->getRoleNames(),
        ]);
    }

    // Private helpers
    /**
     * Check if there is an existed user or create one
     * 
     * @param  string     $provider   some valide provide (facebook, google)
     * @param  SocialUser $socialUser socialite user implementer 
     * @return User       Valid user to authenticate
     */
    private function findOrCreateUser(string $provider, SocialUser $socialUser): User 
    {
        $providerField = $provider . '_id';

        // 1. Find by provider ID
        $user = User::where($providerField, $socialuser->getId())->first();

        if ($user) {
            // Update avatar if changed
            $user->update(['avatar_url' => $socialUser->getAvatar()]);
            return $user;
        }

        // 2. Find by email - link provider to existing account
        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            $user->update([
                $providerField => $socialUser->getId(),
                'avatar_url' => $socialUser->getAvatar(),
            ]);
            return $user;
        }

        // 3. Create new user
        $user = User::create([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
            'email' => $socilUser->getEmail(),
            'password' => null, // social-only account
            $providerField => $socialUser->getId(),
            'avatar_url' => $socailUser->getAvatar(),
            'email_verified_at' => now(), // social emails are pre-verified
        ]);

        // Assign default customer role
        $user->assignRole('customer');

        Log::info("New user via {$provider} social login", ['user_id' => $user->id]);

        return $user;
    }
}
