<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Services\Booking\BookingPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    /**
     * POST /webhooks/paypal
     * 400 only for bad signatures; business errors return 200 so PayPal stops retrying
     * (they are logged and visible in the back office).
     */
    public function __invoke(Request $request, BookingPaymentService $payments): JsonResponse
    {
        try {
            $payments->handleWebhook('paypal', $request);
        } catch (BookingException $e) {
            if ($e->errorCode === 'invalid_signature') {
                return response()->json(['message' => $e->getMessage()], 400);
            }
            Log::warning('PayPal webhook not applied', ['code' => $e->errorCode, 'message' => $e->getMessage()]);
        }

        return response()->json(['message' => 'OK']);
    }
}
