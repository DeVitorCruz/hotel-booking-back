<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Plan;

class PlanController extends Controller
{
    /**
     * GET /platform/plans
     * Public - show on pricing page.
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Plan::where('is_active', true)
                ->orderBy('price_monthly')
                ->get()
        );
    }

    /**
     * GET /platform/plans 
     * 
     * @param  Plan         $plan
     * @return JsonResponse 
     */
    public function show(Plan $plan): JsonResponse 
    {
        return response()->json($plan);
    }
}
