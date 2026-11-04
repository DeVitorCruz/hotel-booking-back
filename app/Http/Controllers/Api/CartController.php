<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Models\Cart;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles shopping cart operations.
 * 
 * Supports both authenticated users and guests (via session_id).
 * Guest carts are merged into the user cart on login.
 */
class CartController extends Controller
{
    /**
     * Get or create a cart for the current user or guest.
     * 
     * For authenticated users, finds or creates a cart linked to user_id.
     * For guests, finds or creates a cart linked to the session ID.
     * 
     * @param Request $request
     * @return Cart
     */
    private function resolveCart(Request $request): Cart
    {
		if ($request->user()) {
			return Cart::firstOrCreate(['user_id' => $request->user()->id]);
		}
		
		$sessionId = $request->header('X-Session-ID') ?? $request->ip(); 
		
		return Cart::firstOrCreate(['session_id' => $sessionId]);
	}
	
	/**
	 * Show the current cart with all items.
	 * 
	 * @param Request $request
	 * @return JsonResponse
	 */
	public function show(Request $request): JsonResponse
	{
		$cart = $this->resolveCart($request);
		$cart->load('items.variant.product');
		
		return response()->json([
			'cart' => new CartResource($cart),
		]);
	}
	
	/**
	 * Add an item to the cart or increase its quantity.
	 * 
	 * If the variant already exists in the cart, its quantity
	 * is incremented. Otherwise a new cart item is created.
	 * 
	 * @param StoreCartItemRequest $request
	 * @return JsonResponse
	 */
	public function addItem(StoreCartItemRequest $request): JsonResponse
	{
		$cart = $this->resolveCart($request);
		$variant = ProductVariant::findOrFail($request->product_variant_id);
		
		// Check stock availability
		if ($variant->stock_quantity < $request->quantity) {
			return response()->json([
				'message' => 'Insufficient stock.',
				'available' => $variant->stock_quantity,
			], 422);
		}
		
		$item = $cart->items()->where('product_variant_id', $variant->id)->first();
		
		if ($item) {
			$item->increment('quantity', $request->quantity);
		} else {
			$cart->items()->create([
				'product_variant_id' => $variant->id,
				'quantity' => $request->quantity,
			]);
		}
		
		$cart->load('items.variant.product');
		
		return response()->json([
			'message' => 'Item added to cart.',
			'cart' => new CartResource($cart),
		]);
	}
	
	/**
	 * Update the quantity of a specific cart item.
	 * 
	 * @param Request  $request
	 * @param CartItem $item
	 * @return JsonResponse
	 */
	public function updateItem(Request $request, CartItem $item): JsonResponse
	{
		$request->validate(['quantity' => 'required|integer|min:1|max:100']);
		
		$cart = $this->resolveCart($request);
		
		if ($item->cart_id !== $cart->id) {
				return response()->json(['message' => 'Unauthorized.'], 403);
		}
		
		$item->update(['quantity' => $request->quantity]);
		$cart->load('items.variant.product');
		
		return response()->json([
			'message' => 'Cart item updated.',
			'cart' => new CartResource($cart),
		]);
	}	
	
	/**
	 * Remove a specific item from the cart.
	 * 
	 * @param Request $request
	 * @param CartItem $item
	 * @return JsonResponse
	 */
	public function removeItem(Request $request, CartItem $item): JsonResponse
	{
		$cart = $this->resolveCart($request);
		
		if ($item->cart_id !== $cart->id) {
		    return response()->json(['message' => 'Unauthorized.'], 403);
		}
		
		$item->delete();
		$cart->load('items.variant.product');
		
		return response()->json([
			'message' => 'Item removed from cart.',
			'cart' => new CartResource($cart),
		]);
	}
	
	/**
	 * Clear all items from the cart.
	 * 
	 * @param Request $request
	 * @return JsonResponse
	 */
	public function clear(Request $request): JsonResponse
	{
		$cart = $this->resolveCart($request);
		$cart->items()->delete();
		
		return response()->json([
			'message' => 'Cart cleared.',
		]);
	}
	
	/**
	 * Merge a guest cart into the authenticated user's cart after login.
	 * 
	 * Takes all item from the guest cart (identified by session_id)
	 * and moves them to the user's cart. If a variant already exists
	 * in the user cart, quantities are summed. The guest cart is 
	 * delete after the merge.
	 * 
	 * @param Request $request Must include X-Session-ID header.
	 * @return JsonResponse
	 */
	public function mergeGuestCart(Request $request): JsonResponse
	{
		$request->validate(['session_id' => 'required|string']);
		
		$guestCart = Cart::where('session_id', $request->session_id)
		      ->with('items')
		      ->first();
		      
		if (!$guestCart || $guestCart->items->isEmpty()) {
			return response()->json(['message' => 'No guest cart to merge.']);
	    }
	    
	    $userCart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
		
		foreach($guestCart->items as $guestItem) {
			$existing = $userCart->items()
			    ->where('product_variant_id', $guestItem->product_variant_id)
			    ->first();
			    
			if ($existing)  {
				$existing->increment('quantity', $guestItem->quantity);
			} else {
				$userCart->items()->create([
					'product_variant_id' => $guestItem->product_variant_id,
					'quantity' => $guestItem->quantity,
				]);
			}
		}
		
		$guestCart->delete();
		$userCart->load('items.variant.product');
		
		return response()->json([
			'message' => 'Guest cart merged successfully.',
			'cart' => new CartResource($userCart),
		]);
	}
}
