<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomTypeResource;
use App\Models\RoomType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoomTypeController extends Controller
{
    /** GET /room-types */
    public function index(): AnonymousResourceCollection
    {
        return RoomTypeResource::collection(
            RoomType::query()->active()->with(['images', 'amenities'])
                ->orderBy('sort_order')->orderBy('base_price')->get()
        );
    }

    /** GET /room-types/{slug} */
    public function show(string $slug): RoomTypeResource
    {
        return new RoomTypeResource(
            RoomType::query()->active()->with(['images', 'amenities'])->where('slug', $slug)->firstOrFail()
        );
    }
}
