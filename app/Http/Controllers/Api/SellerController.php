<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SellerResource;
use Illuminate\Support\Str;
use App\Http\Requests\StoreSellerRequest;
use App\Models\Seller;
use Illuminate\Http\JsonResponse;

class SellerController extends Controller
{
    /**
     * Store a newly created seller profile.
     * 
     * @param StoreSellerRequest $request seller to store.
     * @return JsonResponse 201 created seller profile,
     *                      409 if seller already exist. 
     */
    public function store(StoreSellerRequest $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user->seller) {
            return response()->json([
                'message' => 'You already have a seller profile.',
            ], 409);
        }

        $validatedData = $request->validated();
        $validatedData['slug'] = Str::slug($validatedData['store_name']);
        $validatedData['user_id'] = $user->id;
        $validatedData['status'] = 'pending';

        $seller = Seller::create($validatedData);

        return response()->json([
            'message' => 'Seller onboading request submitted. Awaiting approval.',
            'seller' => new SellerResource($seller),
        ], 201);
    }


    /**
     * GET /seller/profile
     * View own seller store profile.
     * 
     * @param  Request      $request seller to show.
     * @return JsonResponse 200 view own seller profile,
     *                      404 If the user doesn't have seller profile. 
     */
    public function show(Request $request): JsonResponse 
    {
        $seller = $request->user()->seller;

        if (!$seller) {
            return response()->json([
                'message' => 'You do not have a seller profile yet.',
            ], 404);
        }

        return response()->json(new SellerResource($seller->load('user')));
    }

    /**
     * PATCH /seller/profile
     * Update own seller store profile (active sellers only).
     * 
     * @param  Request      $request seller to update.
     * @return JsonResponse 200 view own seller profile,
     *                      404 If the user doesn't have seller profile. 
     *                      403 If unactive sellers try update their profile.
     */
    public function update(Request $request): JsonResponse 
    {
        $seller = $request->user()->seller;

        if (!$seller) {
            return response()->json([
                'message' => 'You do not have a seller profile.'
            ], 404); 
        }

        if (!$seller->isActive) {
            return response()->json([
                'message' => 'You do not have a seller profile.',
            ], 404);
        }

        $data = $request->validate([
            'store_name' => 'sometimes|string|max:191|unique:sellers,store_name,' . $seller->id,
            'description' => 'nullable|string',
            'store_logo' => 'nullable|image|max:2048',
            'store_banner' => 'nullable|image|max:4096',
            'is_marketplace' => 'nullable|boolean',
        ]);

        if ($request->hasFile('store_logo')) {
            $data['store_logo'] = $request->file('store_logo')->store('sellers/logos', 'public');
        }

        if ($request->hasFile('store_banner')) {
            $data['store_banner'] = $request->file('store_banner')->store('sellers/banners', 'public');
        }

        if (isset($data['store_name'])) {
            $data['slug'] = Str::slug($data['store_name']);
        }

        $seller->update($data);

        return response()->json([
            'message' => 'Seller profile updated success fully.',
            'seller' => new SellerResource($seller->fresh()),
        ]);
    }
}
