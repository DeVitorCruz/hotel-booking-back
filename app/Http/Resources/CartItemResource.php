<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats a single cart item for API responses.
 * Includes variant details and computed subtotal
 */
class CartItemResource extends JsonResource
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
			'product_variant_id' => $this->product_variant_id,
			'quantity' => $this->quantity,
			'variant' => new ProductVariantResource($this->whenLoaded('variant')),
			'subtotal' => $this->whenLoaded('variant', fn() => 
				round($this->quantity*$this->variant->price, 2)
			),
        ];
    }
}
