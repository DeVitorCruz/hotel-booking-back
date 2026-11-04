<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RoomType */
class RoomTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'max_adults' => $this->max_adults,
            'max_children' => $this->max_children,
            'max_occupancy' => $this->max_occupancy,
            'beds' => $this->beds,
            'size_sqm' => $this->size_sqm,
            'base_price' => $this->base_price,
            'currency' => app('hotel.settings')->currency(),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($i) => [
                'url' => $i->url, 'alt' => $i->alt ?? $this->name,
            ])),
            'amenities' => $this->whenLoaded('amenities', fn () => $this->amenities->map(fn ($a) => [
                'name' => $a->name, 'slug' => $a->slug, 'icon' => $a->icon,
            ])),
        ];
    }
}
