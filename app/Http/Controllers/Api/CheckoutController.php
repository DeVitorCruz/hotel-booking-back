<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\Payment\CheckoutService;
use App\Services\Payment\GatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(private CheckoutService $checkoutService) {} 

    /**
     * GET /checkout/options
     * Returns available gateways, methods and saved address.
     * 
     * @param  Request      $request
     * @return JsonResponse 200 ok
     */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        return response()->json([
            'gateways' => array_map(fn($g) => [
                'id' => $g,
                'methods' => GatewayFactory::methods($g),
            ], GatewayFactory::supported()),
            'saved_address' => $profile ? [
                'address_line1' =>  $profile->address_line1,
                'address_line2' => $profile->address_line2,
                'city' => $profile->city,
                'state' => $profile->state,
                'postal_code' => $profile->postal_code,
                'country' => $profile->country,
            ] : null,
            'recipient_name' => $user->name,
            'recipient_phone' => $profile?->phone,
        ]);
    }

    /**
     * POST /checkout
     * Initiate checkout - creates order + payment.
     * 
     * @param  Request      $request
     * @return JsonResponse 201, successfully created 
     *                      422  Unprocessed request (cart empty) 
     */
    public function store(Request $request): JsonResponse 
    {
        $data = $request->validate([
            // Gateway + method
            'gateway' => 'required|in:mercadopago,pagseguro',
            'method' => 'required|in:pix,boleto,card',

            // Shipping address (optional if profile has one)
            'address_line1' => 'nullable|string|max:191',
            'address_line2' => 'nullable|string|max:191',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',

            // Recipient
            'recipient_name' => 'nullable|string|max:191',
            'recipient_phone' => 'nullable|string|max:30',

            // Payer info (some gateways require CPF)
            'payer_name' => 'nullable|string|max:191',
            'payer_email' => 'nullable|email',
            'payer_cpf' => 'nullable|string|max:14',

            // Card specific (token from gateway JS SDK)
            'card_token' => 'nullable|string',
            'installments' => 'nullable|integer|min:1|max:12',

            // Order notes
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)
            ->with('items.variant.product')->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        try {
            $result = $this->checkoutService->initiate(
                user: $user,
                cart: $cart,
                gateway: $data['gateway'],
                method: $data['method'],
                data: $data,
            ); 

            return response()->json([
                'message' => 'Order created successfully.',
                'order' => $result['order'],
                'payment' => $result['payment'],
                'payment_details' => $result['payment']->payment_details,
            ], 201); 
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /checktout/{order}
     * Get payment status for an order.
     * 
     * @param  Request           $request
     * @param  \App\Models\Order $order
     * @return JsonResponse      200, ok
     *                           403, Unauthorized
     */
    public function show(Request $request, \App\Models\Order $order): JsonResponse
    {
        $user = $request->user();

        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'order' => $order->load('payment'),
            'payment' => $order->payment,
        ]);
    }
}
