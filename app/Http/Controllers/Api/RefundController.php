<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Payment;
use App\Models\Refund;

class RefundController extends Controller
{
    /**
     * GET /admin/refunds
     * List all refunds - filterable by status.
     * 
     * @param  Request      $request     
     * @return JsonResponse 200, ok
     */
    public function index(Request $request): JsonResponse
    {
        return response()->Json(
            Refund::with([
                'payment',
                'order',
                'requestedBy:id,name,email',
                'processed:id,name',
            ])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
        );
    }

    /**
     * POST /admin/refunds
     * Request a refund for a paid order - admin only. 
     * 
     * @param  Request      $request
     * @return JsonResponse 201, request stored
     *                      422, bad request (only paid payments can be refunded and refund amount cannot be exceed the payment amount)
     *                      409, conflict (refund already exists)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
        ]);

        $payment = Payment::findOrFail($data['payment_id']);

        if (!$payment->isPaid()) {
            return response()->json([
                'message' => 'Only paid payments can be refunded.'
            ], 422);
        }

        if ($payment->refund) {
            return response()->json([
                'message' => 'A refund already exists for this payment.'
            ], 409);
        }

        if ($data['amount'] > $payment->amount) {
            return response()->json([
                'message' => 'Refund amount cannot exceed the payment amount.'
            ], 422);
        }

        $refund = Refund::create([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'amount' => $data['amount'],
            'status' => 'pending',
            'reason' => $data['reason'],
            'requested_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Refund request created.',
            'refund' => $refund->load(['payment', 'requestedBy:id,name']),
        ], 201); 
    }

    /**
     * GET /admin/refunds/{refund}
     * View single refund.
     * 
     * @param  Refund       $refund
     * @return JsonResponse 200, ok
     */
    public function show(Refund $refund): JsonResponse
    {
        return response()->json(
            $refund->load([
                'payment',
                'order',
                'requestedBy:id,name,email',
                'processedBy:id,name',
            ])
        );
    }

    /**
     * PATCH /admin/refunds/{refund}/approve
     * Approve and process a refund - marks payment as refunded.
     * 
     * @param  Request      $request
     * @param  Refund       $refund 
     * @return JsonResponse 200, ok
     *                      422, Bad reuest (only pending can be approved)
     */
    public function approve(Request $request, Refund $refund): JsonResponse 
    {
        if (!$refund->isPending()) {
            return response()->json([
                'message' => 'Only pending refunds can be approved.',
            ], 422);
        }

        $data = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $refund->update([
            'status' => 'processed',
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
            'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        // Update payment and order status
        $refund->payment->update(['status' => 'refunded']);
        $refund->order->update(['status' => 'refunded']);

        // NOTE: actual gateway refund API call goes here when credentials ready
        // GatewayFactory::make($refund->payment->gateway)
        //     ->refundPayment($refund->payment->gateway_id, $refund->amount);

        return response()->json([
            'message' => 'Refund approved and processed.',
            'refund' => $refund->fresh()->load(['processedBy:id,name']),
        ]); 
    }

    /**
     * PATCH /admin/refunds/{refund}/reject
     * Reject a pending refund request.
     * 
     * @param  Request      $request
     * @param  Refund       $refund
     * @return JsonResponse 200, ok
     *                      422, bad request (only pending refunds can be rejected)
     */
    public function reject(Request $request, Refund $refund): JsonResponse 
    {
        if (!$refund->isPending()) {
            return response()->json([
                'message' => 'Only pending refunds can be rejected.',
            ], 422);
        }

        $data = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $refund->update([
            'status' => 'rejected',
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
            'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Refund rejected.',
            'refund' => $refund->fresh(),
        ]);
    }
}
