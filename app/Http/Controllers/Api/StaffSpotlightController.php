<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffSpotlight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffSpotlightController extends Controller
{
    /**
     * GET /spotlights - public listing (visible only)
     * 
     * @return JsonResponse get all visible the spotlights list
     */
    public function index(): JsonResponse
    {
        return response()->json(
            StaffSpotlight::where('is_visible', true)
                ->orderBy('display_order')->get()
        );
    }

    /**
     * GET /admin/spotlights - full listing incl. hidden (admin/owner)
     * 
     * @return JsonResponse get all the spotlights list
     */
    public function adminIndex(): JsonResponse 
    {
        return response()->json(
            StaffSpotlight::with('user:id,name,email')
                ->orderBy('display_order')->get()
        ); 
    }

    /**
     * POST /admin/spotlights - create entry (admin/owner)
     * user_id is optional - null for static/manual entries.
     * 
     * @return JsonResponse 201 created, create spotlight memeber successfully.
     */
    public function store(Request $request): JsonResponse
    {

        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:191',
            'role_title' => 'required|string|max:191',
            'bio' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|max:2048',
            'linkedin' => 'nullable|url',
            'twitter' => 'nullable|url',
            'display_order' => 'nullable|integer|min:0',
            'is_visible' => 'nullable|boolean',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo' ] = $request->file('photo')->store('spotlights', 'public');
        }   

        $spotlight = StaffSpotlight::create($data);

        return response()->json($spotlight, 201);
    }

    /**
     * PATCH /admin/spotlights/{spotlight} - update entry
     * 
     * @param  Request        $request   user request
     * @param  StaffSpotlight $spotlight spotlight to be updated
     * @return JsonResponse   200 ok, the spotlight updated successfully
     */
    public function update(Request $request, StaffSpotlight $spotlight): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'name' => 'sometimes|string|max:191',
            'role_title' => 'sometimes|string|max:191',
            'bio' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|max:2048',
            'linkedin' => 'nullable|url',
            'twitter' => 'nullable|url',
            'display_order' => 'nullable|integer|min:0',
            'is_visible' => 'nullable|boolean'
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('spotlights', 'public');
        }

        $spotlight->update($data);
 
        return response()->json($spotlight->fresh());
    }

    /**
     * DELETE /admin/spotlights/{spotlight} - remove entry
     * 
     * @param  StaffSpotlight $spotlight
     * @return JsonResponse   204 no content, spotlight item deleted sucessfully
     */
    public function destroy(StaffSpotlight $spotlight): JsonResponse
    {
        $spotlight->delete();

        return response()->json(null, 204);
    }
}
