<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = auth()->user();
        return $user
            && $user->hasPermissionTo('products.create') 
            && $user->seller
            && $user->seller->isActive();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:191',
            'slug' => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('products', 'slug')->ignore($this->product)
            ],
            'description' => 'nullable|string',
			'category_id' => 'nullable|exists:tenant.categories,id',
            'variants' => 'required|array',
            'variants.*.sku' => 'required|string|distinct|max:191|unique:tenant.product_variants,sku',
            'variants.*.price' => 'required|numeric|min:0.01',
            'variants.*.stock_quantity' => 'required|integer|min:0',
            'variants.*.image_path' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'variants.*.attributes' => 'nullable|array',
            'variants.*.attributes.*.name' => 'required_with:variants.*.attributes|string',
            'variants.*.attributes.*.value' => 'required_with:variants.*.attributes|string',
        ];
    }
}
