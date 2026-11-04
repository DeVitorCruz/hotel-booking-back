<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats a single order item snapshot for API responses.
 * Uses stored snapshot data, not live product data.
 */
class OrderItemResource extends JsonResource
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
			'product_name' => $this->product_name,
			'variant_sku' => $this->variant_sku,
			'unit_price' => $this->unit_price,
			'quantity' => $this->quantity,
			'subtotal' => round($this->unit_price * $this->quantity, 2),
			'image_path' => $this->image_path,
			'attributes' => $this->attributes,
			'seller' => new SellerResource($this->whenLoaded('seller')),
        ];
    }
}
