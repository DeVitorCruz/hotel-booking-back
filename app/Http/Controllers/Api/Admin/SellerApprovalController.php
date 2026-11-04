<?php
 namespace App\Http\Controllers\Api\Admin;

 use App\Http\Controllers\Controller;
 use App\Http\Resources\SellerResource;
 use App\Models\Seller;
 use Illuminate\Http\JsonResponse;
 use Illuminate\Http\Request;

 /**
  * Handles admin approval and rejection of seller onboarding requests.
  * 
  * All endpoints require authentication and the 'admin/owner' role.
  */
 class SellerApprovalController extends Controller
 {
    /**
     * List all seller profiles pending admin review (FIFO).
     *  
     * @return JsonResponse 200 with a collection of pending SellerResource.
     */
    public function index(): JsonResponse
    {
        $sellers = Seller::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'sellers' => SellerResource::collection($sellers),
        ]);
    }

    /**
     * Approve a pending - sets status to 'active' and assigns seller role.
     *  
     * @param Seller $seller The seller to approve (route model binding).	
     * @return JsonResponse 200 with updated SellerResource,
     *                      422 if the seller is not in pending state.
     */
    public function approve(Seller $seller): JsonResponse
    {
        if ($seller->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending sellers can be approved.',
            ], 422);
        }

        $seller->update(['status' => 'active']);

        // Assign seller role to the user
        $seller->user->assignRole('seller');

        return response()->json([
            'message' => 'Seller approved successfully.',
            'seller' => new SellerResource(
                $seller->fresh('user')
            ),
        ]);
    }

    /**
     * Reject a pending seller - stores rejection reason.
     *
     * @param Seller $seller The seller to reject (route model binding).
     * @return JsonResponse 200 with updated SellerResource,
     *                      422 if the seller is not in pending state.
     */
    public function reject(Request $request, Seller $seller): JsonResponse
    {
        if ($seller->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending sellers can be rejected.',
            ], 422);
        }

        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $seller->update([
            'status' => 'rejected',
            'rejection_reason' => $data['rejection_reason']?? null,
        ]);

        return response()->json([
            'message' => 'Seller rejected.',
            'seller' => new SellerResource(
                $seller->fresh('user')
            ),
        ]);
    }

    /**
     * Suspend an active seller.
     * 
     * @param Request $request seller to suspender.
     * @param Seller  $seller  The seller to match.
     * @return JsonResponse 200 with updated SellerResource,
     *                      422 if the seller is not active state. 
     */
    public function suspend(Request $request, Seller $seller) : JsonResponse
    {
        if ($seller->status !== 'active') {
            return response()->json([
                'message' => 'Only active sellers can be suspended.',
            ], 422);
        }

        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $seller->update([
            'status' => 'suspended',
            'rejection_reason' => $data['rejection_reason']?? null,
        ]);

        // Remove seller role while suspended
        $seller->user->removeRole('seller');

        return response()->json([
            'message' => 'Seller suspended.',
            'seller' => new SellerResource($seller->fresh('user')),            
        ]);
    }
}
