<?php
  namespace App\Http\Resources;

  use Illuminate\Http\Request;
  use Illuminate\Http\Resources\Json\JsonResource;

  class CategoryResource extends JsonResource 
  {
     public function toArray(Request $request): array
     {
         return [
             'id' => $this->id,
             'name' => $this->name,
             'slug' => $this->slug,
             'description' => $this->description,
             'image_path' => $this->image_path,
             'status' => $this->status,
             'is_active' => $this->is_active,
             'parent_id' => $this->parent_id,
             'parent' => new CategoryResource($this->whenLoaded('parent')),
             'children' => CategoryResource::collection($this->whenLoaded('allChildren')),
             'suggested_by' => $this->whenLoaded('suggestedBy', fn() => $this->suggestedBy->name),
             'approved_by' => $this->whenLoaded('approvedBy', fn() => $this->approvedBy?->name),
             'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
     }
}
