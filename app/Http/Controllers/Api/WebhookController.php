<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(private CheckoutService $checkoutService) {}

    /**
     * POST /webhooks/mercadopago
     * 
     * @param  Request      $request
     * @return JsonResponse 200, ok
     *                      400, bad request
     */
    public function mercadopago(Request $request): JsonResponse 
    {
        Log::info('MercadoPago webhook received', $request->all());   

        try {
            $this->checkoutService->handleWebhook('mercadopago', $request);
            return response()->json(['message' => 'OK'], 200);
        } catch (\RuntimeException $e) {
            Log::error('Mercadopago webhook error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /webhooks/pagseguro
     * 
     * @return JsonResponse 200, ok
     *                      400, bad request                
     */
    public function pagseguro(Request $request): JsonResponse
    {
        Log::info('Pagseguro webhook received', $request->all());

        try {
            $this->checkoutService->handleWebhook('pagseguro', $request);
            return response()->json(['message' => 'OK'], 200);
        } catch (\RuntimeException $e) {
            Log::error('Pagseguro webhook error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
