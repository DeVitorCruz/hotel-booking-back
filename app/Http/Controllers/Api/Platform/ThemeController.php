<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\AppType;
use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ThemeController extends Controller
{
    /**
     * GET /platform/themes
     * Public - list active themes.
     * Optional: ?app_type=ecommerce
     * 
     * @param  Request      $request
     * @return JsonResponse 200, list all actived theme
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Theme::with('appType')
                ->where('is_active', true)
                ->when($request->app_type, fn($q, $t) => 
                    $q->whereHas('appType', fn($q) => $q->where('slug', $t))
                )->get()
        );
    }
}
