<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats the full cart with its items and total for response.
 */
class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
			'id' => $this->id,
			'user_id' => $this->user_id,
			'items' => CartItemResource::collection($this->whenLoaded('items')),
			'total' => $this->whenLoaded('items', fn() => 
				round($this->items->sum(fn($item) => 
					$item->quantity*($item->variant?->price ?? 0)
				), 2)
			),
			'items_count' => $this->whenLoaded('items', fn() => $this->items->count()),
			'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
