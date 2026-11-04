<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Handles order status transitions for admins and sellers.
 * 
 * Enforces the valid status flow:
 *   pending -> paid -> processing -> shipped -> delivered
 *                                            -> cancelled / refunded
 * 
 * All endpoints require authentication and the 'admin' role via   
 * the EnsureUserIsAdmin middleware applied at the route level.
 */
class OrderStatusController extends Controller
{
    /**
     * Valid status transitions map.
     * Defines which statuses can follow each current status.
     * 
     * @var array<string, array<string>>
     */
    private array $transitions = [
		'pending' => ['paid', 'cancelled'],
		'paid' => ['processing', 'refunded', 'cancelled'],
		'processing' => ['shipped', 'cancelled'],
		'shipped' => ['delivered'],
		'delivered' => ['refunded'],
		'cancelled' => [],
		'refunded' => [],
    ];
    
    /**
     * List all orders with optional status filter.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
		$query = Order::with(['items.seller', 'user', 'statusHistory']);
		
		if ($request->has('status')) {
			$query->where('status', $request->status);
		}
		
		$orders = $query->orderBy('created_at', 'desc')
		    ->paginate($request->get('per_page', 15));
		
		return response()->json([
			'orders' => OrderResource::collection($orders),
			'pagination' => [
				'total' => $orders->total(),
				'per_page' => $orders->perPage(),
				'current_page' => $orders->currentPage(),
				'last_page' => $orders->lastPage(),
			],
		]);
	}
	
	/**
	 * Transition an order to a new status.
	 * 
	 * Validates that the requested transition is allowed based on
	 * the current status. Records the change in status history.
	 * 
	 * @param Request $request
	 * @param Order   $order
	 * @return JsonResponse
	 */
	public function updateStatus(Request $request, Order $order): JsonResponse
	{
		$request->validate([
			'status' => 'required|string|in:'.implode(',', Order::STATUSES),
			'comment' => 'nullable|string|max:500',
		]);
		
		$newStatus = $request->status;
		$allowed = $this->transitions[$order->status] ?? [];
		
		if (!in_array($newStatus, $allowed)) {
		    return response()->json([
				'message' => "Cannot transition from '{$order->status}' to '{$newStatus}'.",
				'allowed' => $allowed,
		    ], 422);	
		}
		
		$order->updateStatus($newStatus, $request->user()->id, $request->comment);
		$order->load('items.seller', 'statusHistory.changedBy', 'user');
		
		return response()->json([
		    'message' => 'Order status updated successfully.',
		    'order' =>  new OrderResource($order),
		]);
	}
}
