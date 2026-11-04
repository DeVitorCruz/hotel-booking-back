<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 *  Handles order placement and management for buyers.
 * 
 * Orders are created from the buyer's cart at checkout.
 * Product data is snapshotted at purchase time for historical accuracy.
 */
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
		$orders = Order::with(['items.seller', 'statusHistory'])
		    ->where('user_id', $request->user()->id)
		    ->orderBy('created_at', 'desc')
		    ->paginate($request->get('per_page', 10));
		
		return response()->json([
			'orders' => OrderResource::collection($orders),
			'pagination' => [
				'total' => $orders->total(),
				'per_page' => $orders->perPage(),
				'current_page' => $orders->currentPage(),
				'last_page' => $orders->lastPage(),
			]
		]);
	}
	
	/**
	 * Place an order from the buyer's current cart.
	 * 
	 * Flow:
	 *  1. Validate the cart is not empty.
	 *  2. Verify stock availability for all items.
	 *  3. Create the order and snapshot all item data.
	 *  4. Deduct stock from each variant.
	 *  5. Record the initial 'pending' status in history.
	 *  6. Clear the cart.
	 * 
	 * All steps run in a single DB transaction for data integrity.
	 * 
	 * @param  StoreOrderRequest $request
	 * @return JsonResponse
	 */
	public function store(StoreOrderRequest $request): JsonResponse
	{
		$cart = Cart::where('user_id', $request->user()->id)
		    ->with('items.variant.product.seller')
		    ->first();
		    
		if (!$cart || $cart->items->isEmpty()) {
		    return response()->json([
			    'message' => 'Your cart is empty.'
		    ], 422);
	    }
	     
	    try {
			DB::beginTransaction();
			
			// Verify stock and calculate total
			$total = 0;
			
			foreach($cart->items as $item) {
				if (!$item->variant || $item->variant->stock_quantity < $item->quantity) {
				    DB::rollBack();
				    
				    return response()->json([
						'message' => 'Insufficient stock for: '. ($item->variant?->sku ?? 'unknown'),
				    ], 422);
				}
				$total += $item->variant->price * $item->quantity;
			}
			
			// Create the order
			$order = Order::create([
				'user_id' => $request->user()->id,
				'status' => 'pending',
				'total_amount' => round($total, 2),
				'shipping_address' => $request->validated('shipping_address'),
				'notes' => $request->validated('notes'),
			]);
			
			// Snapshot each item and deduct stock
			foreach ($cart->items as $item) {
				$variant = $item->variant;
				$product = $variant->product;
				
				// Build attributes snapshot
				$attributes = $variant->attributeValues
				     ->map(fn($av) => [
						 'name' => $av->attribute->name,
						 'value' => $av->value,  
				     ])->toArray();
				
				$order->items()->create([
					'product_variant_id' => $variant->id,
					'seller_id' => $product->seller_id,
					'product_name' => $product->name,
					'variant_sku' => $variant->sku,
					'unit_price' => $variant->price,
					'quantity' => $item->quantity,
					'image_path' => $variant->image_path,
					'attributes' => $attributes,
				]);
				
				// Deduct stock
				$variant->decrement('stock_quantity', $item->quantity);
			}
			
			// Record initial status in history 
			$order->statusHistory()->create([
				'status' => 'pending',
				'changed_by' => $request->user()->id,
				'comment' => 'Order placed.',
			]);
			
			// Clear the cart
			$cart->items()->delete();
			
			DB::commit();
			
			$order->load('items.seller', 'statusHistory.changedBy');
			
			return response()->json([
				'message' => 'Order placed successfully.',
				'order' => new OrderResource($order),
			], 201);
				
		} catch (\Exception $e) {
			DB::rollBack();
			return response()->json([
				'message' => 'Failed to place order.',
				'error' => $e->getMessage(),
			], 500);
		} 
	}
	
	/**
	 * Show a single order with full details.
	 * 
	 * @param Request $request
	 * @param Order   $order
	 * @return JsonResponse
	 */
	public function show(Request $request, Order $order): JsonResponse
	{
		if ($order->user_id !== $request->user()->id) {
			return response()->json(['message' => 'Unauthorized.'], 403);
		}
		
		$order->load('items.seller', 'statusHistory.changedBy');
		
		return response()->json([
			'order' => new OrderResource($order),
		]);
	}
	
	/**
	 * Cancel a pending order.
	 * 
	 * Only orders in 'pending' or 'paid' status can be cancelled.
	 * Stock is restored when an order is cancelled.
	 * 
	 * @param Request $request
	 * @param Order   $order
	 * @return JsonResponse
	 */
	public function cancel(Request $request, Order $order): JsonResponse
	{
		if ($order->user_id !== $request->user()->id) {
			return response()->json(['message' => 'Unauthorized.'], 403);	
		}
		
		if (!in_array($order->status, ['pending', 'paid'])) {
			return response()->json([
				'message' => 'Only pending or paid orders can be canceller.'
			], 422);
		}
		
		try {
			DB::beginTransaction();
			
			// Restore stock for each item
			
			foreach($order->items as $item) {
				if ($item->variant) {
					$item->variant->increment('stock_quantity', $item->quantity);
				}
			}
			
			$order->updateStatus('cancelled', $request->user()->id, 'Cancelled by buyer.');
			
			DB::commit();
			
			return response()->json([
			    'message' => 'Order cancelled successfully.',
			    'order' => new OrderResource($order->load('items', 'statusHistory')),
			]);
			
		} catch (\Exception $e) {
			DB::rollBack();
			
			return response()->json([
				'message' => 'Failed to cancel order.',
				'error' => $e->getMessage(),
			], 500);
		}
	}
}
