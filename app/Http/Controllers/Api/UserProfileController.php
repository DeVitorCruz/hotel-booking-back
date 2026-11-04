<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * GET /profile
     * Return the authenticated user with their profile.
     * Creates an empty profile if none exists yet.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load('profile');

        return response()->json([
            'user' => $user,
            'profile' => $user->profile,
            'roles' => $user->getRoleNames(),
        ]);
    }

    /**
     * PUT /profile
     * Create or update the authenticated user's profile.
     * 
     * Also allows updating the user's name.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            // User fields
            'name' => 'sometimes|string|max:191',
            // Profile fields
            'avatar' => 'nullable|image|max:2048',
            'bio' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:30',
            'address_line1' => 'nullable|string|max:191',
            'address_line2' => 'nullable|string|max:191',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'website' => 'nullable|url|max:191',
            'linkedin' => 'nullable|url|max:191',
            'twitter' => 'nullable|url|max:191',
            'instagram' => 'nullable|url|max:191',
        ]);

        $user = $request->user();

        // Update user name if provided
        if (isset($data['name'])) {
            $user->update(['name' => $data['name']]);
        }

        // Upate user name if provided
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')
                ->store('avatars', 'public');
        }

        // Remove user-only fields before upserting profile
        $profileData = collect($data)
            ->except(['name'])
            ->toArray();

        // Create or update profile (lazy creation)
        $profile = $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $profileData
        );

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
            'profile' => $profile,
            'roles' => $user->getRoleNames(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
