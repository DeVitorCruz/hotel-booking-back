<?php

namespace App\Services\Payment;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class CheckoutService
{
    /**
     * Initiate checkout - creates Order + Payment in a transaction.
     * 
     * @param  User   $user
     * @param  Cart   $cart
     * @param  string $gateway mercadopago | pagseguro
     * @param  string $method  pix | boleto | card
     * @param  array  $data    shipping address + payer info
     * @return array  ['order' => Order, 'payment' => Payment]         
     */
    public function initiate(User $user, Cart $cart, string $gateway, string $method, array $data): array 
    {
        return DB::transaction(function () use ($user, $cart, $gateway, $method, $data) {

            // 1. Validate cart is not empty
            $items = $cart->items()->with('variant')->get();        

            if ($items->isEmpty()) {
                throw new \RuntimeException('Cart is empty.');
            }

            // 2. Build shipping address
            // Priority: checkout override -> saved profile address
            $profile = $user->profile;
            $shippingAddress = [
                'address_line1' => $data['address_line1'] ?? $profile->address_line1,
                'address_line2' => $data['address_line2'] ?? $profile->address_line2 ,
                'city' => $data['city'] ?? $profile->city,
                'state' => $data['state'] ?? $profile->state,
                'postal_code' => $data['postal_code'] ?? $profile->postal_code,
                'country' => $data['country'] ?? $profile->country ?? 'BR',
            ];

            // Validate address is complete
            if(empty($shippingAddress['address_line1']) || empty($shippingAddress['city']) || empty($shippingAddress['postal_code'])) {
                throw new \RuntimeException(
                    'A complete shipping address is required to checkout.'
                );
            }

            // 3. Calculate total
            $total = $items->sum(fn($item) => 
                $item->variant->price * $item->quantity
            );  

            // 4. Create Order
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total_amount' => $total,
                'shipping_address' => $shippingAddress,
                'recipient_name' => $data['recipient_name'] ?? $user->name,
                'recipient_phone' => $data['recipient_phone'] ?? $profile->phone,
                'notes' => $data['notes'] ?? null,
            ]);

            // 5. Create OrderItems from cart
            foreach ($items as $item) {
                $variant = $item->variant;
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    'seller_id' => $variant->product->seller_id,
                    'product_name' => $variant->product->name,
                    'variant_sku' => $variant->sku,
                    'unit_price' => $variant->price,
                    'quantity' => $item->quantity,
                    'image_path' => $variant->image_path,
                ]);

                // Decrement stock
                $variant->decrement('stock_quantity', $item->quantity);
            }

            // 6. Call gateway
            $gw = GatewayFactory::make($gateway);
            $result = $gw->createPayment($order, $method, $data);

            // 7. Crate Payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'gateway' => $gateway,
                'method' => $method,
                'gateway_id' => $result->gatewayId,
                'gateway_status' => $result->status,
                'amount' => $total,
                'status' => $result->isPaid() ? 'paid' : 'pending',
                'gateway_response' => $result->rawResponse,
                'payment_details' => $result->paymentDetails,
                'paid_at' => $result->isPaid() ? now() : null,
                'expires_at' => $result->expiresAt,
            ]);

            // 8. Clear cart after successful order creation
            $cart->items()->delete();

            Log::info('Checkout initiated', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'gateway' => $gateway,
                'method' => $method,
                'total' => $total,                
            ]);

            return [
                'order' => $order->fresh(),
                'payment' => $payment->fresh(),
            ];
        });
    }

    /**
     * Handle incoming webhook - update payment + order status.
     * 
     * @param  string  $gateway
     * @param  Request $request
     * @return void
     */
    public function handleWebhook(string $gateway, Request $request): void 
    {
        $gw = GatewayFactory::make($gateway);

        if (!$gw->validateWebhook($request)) {
            Log::warning("Invalid {$gateway} webhook signature");
            throw new \RuntimeException('Invalid webhook signature');
        }

        $gatewayId = $gw->parseWebhookGatewayId($request);
        $status = $gw->parseWebhookStatus($request);
        
        if (empty($gatewayId)) {
            Log::warning("Webhook missing gateway ID", $request->all());
            return;
        }

        $payment = Payment::where('gateway_id', $gatewayId)->first();

        if (!$payment) {
            Log::warning("Payment no found for gateway ID: {$gatewayId}");
            return;
        }

        $payment->update([
            'gateway_status' => $status,
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : $payment->paid_at,
        ]);

        // Update order status based on payment
        $orderStatus = match($status) {
            'paid' => 'paid',
            'failed' => 'cancelled',
            'refunded' => 'refunded',
            default => $payment->order_id,
        };

        $payment->order->update(['status' => $orderStatus]);

        Log::info("Webhook processed: {$gateway}", [
            'gateway_id' => $gatewayId,
            'status' => $status,
            'order_id' => $payment->order_id,
        ]);
    }
}
