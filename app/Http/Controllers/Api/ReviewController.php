<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Order;
use App\Models\Review;

class ReviewController extends Controller
{
    /**
     * GET /products/{product}/reviews
     * Approved reviews for a product with average rating.
     * 
     * @param  Product      $product product with approved reviews associated
     * @return JsonResponse 200 reviews successfully retrieved
     */
    public function productReviews(Product $product): JsonResponse
    {
        $reviews = $product->approvedReviews()
            ->with('user:id,name')
            ->latest()
            ->paginate(10);

        return response()->json([
            'average_rating' => round($product->approvedReviews()->avg('rating'), 1),
            'total' => $product->approvedReviews()->count(),
            'reviews' => $reviews,
        ]);
    }

    /**
     * GET /sellers/{sellers}/reviews
     * Approved reviews for a seller with average rating
     * 
     * @param  Seller       $seller seller with approved reviews associated.
     * @return JsonResponse 200 reviews successfully retrieved
     */
    public function sellerReviews(Seller $seller): JsonResponse
    {
        $reviews = $seller->approvedReviews()
            ->with('user:id,name')
            ->latest()
            ->paginate(10);

        return response()->json([
            'average_rating' => round($seller->approvedReviews()->avg('rating'), 1),
            'total' => $seller->approvedReviews()->count(),
            'reviews' => $reviews,
        ]);
    }


    /**
     * POST /products/{product}/reviews
     * Submit a product review - verifid purchase required.
     * 
     * @param  Request      $request, user request to review 
     * @param  Product      $product, product to be reviewed
     * @return JsonResponse 201 new review successfully created
     *                      403 Unauthorized review,
     *                      409 Conflict reviews
     */
    public function storeProductReview(Request $request, Product $product): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'name' => 'required|string|max:120',
            'email' => 'required|email',
            'comment' => 'nullable|string|max:2000',
            'quality' => 'nullable|integer|min:1|max:5',
            'shipping' => 'nullable|integer|min:1|max:5',
            'value' => 'nullable|integer|min:1|max:5',
        ]);

        // Verify order belongs to user and is delivered
        $order = Order::where('id', $data['order_id'])
            ->where('user_id', $user->id)
            ->where('status', 'delivered')
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'You can only review products from delivered orders.',
            ], 403);
        }

        // Verify product was in that order
        $purchased = $order->items()
            ->whereHas('variant', fn($q) => 
                $q->where('product_id', $product->id)
            )->exists();

        if (!$purchased) {
            return response()->json([
                'message' => 'This product was not in the specific order.'
            ], 403);
        }

        // Check for existing reviews
        $existing = Review::where('user_id', $user->id)
            ->where('reviewable_type', Product::class)
            ->where('reviewable_id', $product->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You have already reviewed this product.',
                'review' => $existing,
            ], 409);
        }

        $review = Review::create([
            ...$data,
            'user_id' => $user->id,
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Review submitted. It will appear after admin approval.',
            'review' => $review,
        ], 201);
    }

    /**
     * POST /sellers/{seller}/reviews
     * Submit a seller review - must have a delivered order from that seller.
     * 
     * @param  Request      $request user request review
     * @param  Seller       $seller  seller to be reviewed
     * @return JsonResponse 201 review successfully posted
     *                      403 Unauthorized review
     *                      409 Conflict review
     */
    public function storeSellerReview(Request $request, Seller $seller): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'name' => 'required|string|max:120',
            'email' => 'required|email',
            'comment' => 'nullable|string|max:2000',
            'quality' => 'nullable|integer|min:1|max:5',
            'shipping' => 'nullable|integer|min:1|max:5',
            'value' => 'nullable|integer|min:1|max:5'
        ]);

        // Verify order belongs to user and is delivered
        $order = Order::where('id', $data['order_id'])
            ->where('user_id', $user->id)
            ->where('status', 'delivered')
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'You can only review sellers from delivered orders.',
            ], 403);
        }

        // Verify seller was part of that order
        $purchasedFromSeller = $order->items()
            ->where('seller_id', $seller->id)
            ->exists();

        if (!$purchasedFromSeller) {
            return response()->json([
                'message' => 'You have not purchased from this seller in the specified order.',
            ], 403);
        }

        // Check for existing review
        $existing = Review::where('user_id', $user->id)
            ->where('reviewable_type', Seller::class)
            ->where('reviewable_id', $seller->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You have already reviewed this seller.',
                'review' => $existing,
            ], 409);
        }
        
        $review = Review::create([
            ...$data,
            'user_id' => $uesr->id,
            'reviewable_type' => Seller::class,
            'reviewable_id' => $seller->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Review submitted. It will appear after admin approval.',
            'review' => $review,
        ], 201);
    }

    /**
     * PATCH /reviews/{review}
     * Edit own pending review - locked once approved/rejected.
     * 
     * @param  Request $request user request to update review
     * @param  Review  $review  review to be updated
     * @return JsonResponse 200 review updated successfully
     *                      403 update unauthorized                
     */
    public function update(Request $request, Review $review): JsonResponse 
    {
        $user = $request->user();

        if ($review->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($review->isLocked()) {
            return response()->json([
                'message' => 'This review has been ' . $review->status . ' and can no longer be edited.',
            ], 403);
        }

        $data = $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'name' => 'sometimes|string|max:120',
            'email' => 'sometimes|email',
            'comment' => 'nullable|string|max:2000',
            'quality' => 'nullable|integer|min:1|max:5',
            'shipping' => 'nullable|integer|min:1|max:5',
            'value' => 'nullable|integer|min:1|max:5',
        ]);

        $review->update($data);

        return response()->json([
            'message' => 'Review updated.',
            'review' => $review->fresh(),
        ]);
    }

    /**
     * GET /admin/reviews
     * 
     * @param  Request      $request user request for reviews index 
     * @return JsonResponse 200      retrieve the reviews index
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $reviews = Review::with(['user:id,name,email', 'reviewable', 'approvedBy:id,name'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type, fn($q, $t) => 
                $q->where('reviewable_type', $t === 'product' ? Product::class : Seller::class)
            )->latest()->paginate(20);


        return response()->json($reviews);
    }

    /**
     * PATCH /admin/reviews/{review}/approve
     * 
     * @param  Review       $review  review item to be updated
     * @param  Request      $request user request to update the review item 
     * @return JsonResponse 200      review item successfully updated
     *                      422      Unprocessable request for user 
     */
    public function approve(Request $request, Review $review): JsonResponse
    {
        if (!$review->isPending()) {
            return response()->json(['message' => 'Only pending reviews can be approved.'], 422);
        }

        $review->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json(['message' => 'Review approved.', 'review' => $review->fresh()]);
    }

    /**
     * PATCH /admin/reviews/{review}/reject
     * 
     * @param  Review       $review review item to be rejected
     * @return JsonResponse 200     review item successfully rejected
     *                      422     Unprocessable request for user
     */
    public function reject(Review $review): JsonResponse 
    {

        if (!$review->isPending()) {
            return response()->json(['message' => 'Only pending reviews can be rejected.'], 422);
        }

        $review->update(['status' => 'rejected']);

        return response()->json(['message' => 'Review rejected.', 'review' => $review->fresh()]);
    }

    /**
     * DELETE /admin/reviews/{review}
     * 
     * @param  Review       $review    review item to be destroyed 
     * @return JsonResponse 204 review item destroyed successfully
     */
    public function destroy(Review $review): JsonResponse 
    {
        $review->delete();
        return response()->json(null, 204);
    }
}
