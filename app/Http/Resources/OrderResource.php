<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formts a full order with its items and status history for Api responses.
 */
class OrderResource extends JsonResource
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
			'status' => $this->status,
			'total_amount' => $this->total_amount,
			'shipping_address' => $this->shipping_address,
			'notes' => $this->notes,
			'items' => OrderItemResource::collection($this->whenLoaded('items')),
			'status_history' => $this->whenLoaded('statusHistory', fn() => 
				$this->statusHistory->map(fn($h) => [
					'status' => $h->status,
					'comment' => $h->comment,
					'changed_by' => $h->changedBy?->name,
					'date' => $h->created_at->format('Y-m-d H:i:s'), 
				])
			),
			'created_at' => $this->created_at->format('Y-m-d H:i:s'),
			'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
