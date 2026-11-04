<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WishlistController extends Controller
{
    /**
     * Create or get the wishlist 
     * 
     * @param  Request  $request user request
     * @return Wishlist get user withlist
     */
    private function getOrCreateWishlist(Request $request): Wishlist
    {
        return Wishlist::firstOrCreate(['user_id' => $request->user()->id]);
    }

    /**
     * GET /wishlist - view own wishlist with products
     * 
     * @param  Request      $request user request
     * @return JsonResponse 200 ok, get wishlist's products 
     */
    public function index(Request $request): JsonResponse 
    {
        $wishlist = $this->getOrCreateWishlist($request);

        return response()->json(
            $wishlist->load('products')
        );
    }

    /**
     * POST /wishlist/{productId} - add product to wishlist
     * 
     * @param  Request      $request user request
     * @param  int          $productId product id
     * @return JsonResponse 201 created, product added successfully
     */
    public function add(Request $request, int $productId): JsonResponse
    {
        $wishlist = $this->getOrCreateWishlist($request);

        $item = WishlistItem::firstOrCreate([
            'wishlist_id' => $wishlist->id,
            'product_id' => $productId,
        ]);

        return response()->json([
            'message' => 'Added to wishlist.',
            'item' => $item,
        ], 201);
    }

    /**
     * DELETE /wishlist/{productId} - remove product from wishlist
     * 
     * @param  Request      $request user request
     * @param  int          $productId product id
     * @return JsonResponse 200 ok, product removed successfully
     */
    public function remove(Request $request, int $productId): JsonResponse
    {
        $wishlist = $this->getOrCreateWishlist($request);

        WishlistItem::where('wishlist_id', $wishlist->id)
            ->where('product_id', $productId)->delete();

        return response()->json([
            'message' => 'Removed from wishlist.',
        ]); 
    }

    /**
     * DELETE /wishlist - clear entire wishlist
     * 
     * @param  Request      $request user request
     * @return JsonResponse 200 ok, wishlist successfully deleted
     */
    public function clear(Request $request): JsonResponse
    {
        $wishlist = $this->getOrCreateWishlist($request);
        $wishlist->items()->delete();

        return response()->json([
            'message' => 'Wishlist cleared.',
        ]);
    }
}
