<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\RoomType;
use App\Models\RoomTypeImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Back-office management of room types (the products guests book),
 * their amenities and photos. Inactive types are hidden from the website.
 */
class RoomTypeAdminController extends Controller
{
    /** GET /admin/room-types */
    public function index(): JsonResponse
    {
        return response()->json(
            RoomType::query()->with(['images', 'amenities:id'])->withCount('rooms')
                ->orderBy('sort_order')->orderBy('base_price')->get()
                ->map(fn (RoomType $t) => $this->present($t))
        );
    }

    /** GET /admin/room-types/{roomType} */
    public function show(RoomType $roomType): JsonResponse
    {
        return response()->json($this->present($roomType->load(['images', 'amenities:id'])->loadCount('rooms')));
    }

    /** POST /admin/room-types */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name']);

        $type = RoomType::create($data)->refresh();
        $type->amenities()->sync($request->input('amenity_ids', []));

        return response()->json($this->present($type->load(['images', 'amenities:id'])->loadCount('rooms')), 201);
    }

    /** PATCH /admin/room-types/{roomType} */
    public function update(Request $request, RoomType $roomType): JsonResponse
    {
        $data = $this->validated($request, $roomType);
        if (array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug($data['slug'] ?: ($data['name'] ?? $roomType->name), $roomType->id);
        }

        $roomType->update($data);
        if ($request->has('amenity_ids')) {
            $roomType->amenities()->sync($request->input('amenity_ids', []));
        }

        return $this->show($roomType->refresh());
    }

    /** DELETE /admin/room-types/{roomType} — only when it has never been booked. */
    public function destroy(RoomType $roomType): JsonResponse
    {
        if (Booking::where('room_type_id', $roomType->id)->exists()) {
            throw new BookingException('This room type has bookings. Deactivate it instead of deleting it.', 'has_bookings', 409);
        }
        if ($roomType->rooms()->exists()) {
            throw new BookingException('Move or delete its rooms first.', 'has_rooms', 409);
        }

        foreach ($roomType->images as $image) {
            $this->deleteFile($image);
        }
        $roomType->delete();

        return response()->json(null, 204);
    }

    /** POST /admin/room-types/{roomType}/images — multipart `image` file, or JSON `url`. */
    public function addImage(Request $request, RoomType $roomType): JsonResponse
    {
        $request->validate([
            'image' => ['required_without:url', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'url' => ['required_without:image', 'nullable', 'url', 'max:500'],
            'alt' => ['nullable', 'string', 'max:191'],
        ]);

        $path = $request->hasFile('image')
            ? $request->file('image')->store("room-types/{$roomType->id}", 'public')
            : $request->input('url');

        $image = $roomType->images()->create([
            'path' => $path,
            'alt' => $request->input('alt'),
            'sort_order' => (int) $roomType->images()->max('sort_order') + 1,
        ]);

        return response()->json(['id' => $image->id, 'url' => $image->url, 'alt' => $image->alt, 'sort_order' => $image->sort_order], 201);
    }

    /** PATCH /admin/room-types/{roomType}/images/order { ids: [..] } — first image is the cover. */
    public function orderImages(Request $request, RoomType $roomType): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        foreach (array_values($ids) as $i => $id) {
            $roomType->images()->whereKey($id)->update(['sort_order' => $i]);
        }

        return $this->show($roomType->refresh());
    }

    /** DELETE /admin/room-types/{roomType}/images/{image} */
    public function deleteImage(RoomType $roomType, RoomTypeImage $image): JsonResponse
    {
        abort_unless($image->room_type_id === $roomType->id, 404);

        $this->deleteFile($image);
        $image->delete();

        return response()->json(null, 204);
    }

    // ------------------------------------------------------------------

    private function validated(Request $request, ?RoomType $existing = null): array
    {
        $req = $existing ? 'sometimes' : 'required';

        $data = $request->validate([
            'name' => [$req, 'string', 'max:191'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:191', 'alpha_dash'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'max_adults' => [$req, 'integer', 'min:1', 'max:20'],
            'max_children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'max_occupancy' => [$req, 'integer', 'min:1', 'max:30'],
            'beds' => ['sometimes', 'nullable', 'string', 'max:191'],
            'size_sqm' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'base_price' => [$req, 'numeric', 'min:0', 'max:99999999'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'amenity_ids' => ['sometimes', 'array'],
            'amenity_ids.*' => ['integer', Rule::exists('amenities', 'id')],
        ]);

        unset($data['amenity_ids']);

        return $data;
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'room';
        $slug = $base;
        $i = 2;
        while (RoomType::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-" . $i++;
        }

        return $slug;
    }

    private function deleteFile(RoomTypeImage $image): void
    {
        if (! str_starts_with($image->path, 'http')) {
            Storage::disk('public')->delete($image->path);
        }
    }

    private function present(RoomType $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'short_description' => $t->short_description,
            'description' => $t->description,
            'max_adults' => $t->max_adults,
            'max_children' => $t->max_children,
            'max_occupancy' => $t->max_occupancy,
            'beds' => $t->beds,
            'size_sqm' => $t->size_sqm,
            'base_price' => $t->base_price,
            'is_active' => $t->is_active,
            'sort_order' => $t->sort_order,
            'rooms_count' => $t->rooms_count ?? $t->rooms()->count(),
            'amenity_ids' => $t->amenities->pluck('id')->values(),
            'images' => $t->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url, 'alt' => $i->alt, 'sort_order' => $i->sort_order])->values(),
        ];
    }
}
