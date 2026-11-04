<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes product update requests.
 * 
 * All fields are optional (sometimes) since this is a partial update.
 * Authorization ensures only the seller who owns the product can update it.
 */
class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the authenticated user is authorized to update this product.
     * 
     * The user must be authenticated and have an active seller profile,
     * and the product must belong to that seller.
     * 
     * @return bool True if the user owns the product, false otherwise.
     */
    public function authorize(): bool
    {
        $user = auth()->user();
		$product = $this->route('product');
        return $user 
            && $user->seller 
            && $product->seller_id === $user->seller->id
            && $user->hasPermissionTo('products.edit-own');
    }

    /**
     * Get the validation rules for the product update.
     *
     * All fields use 'sometimes' so only provided fields are validated.
     * The slug must be unique across products, ignoring the current product.
     * 
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:191',
            'category_id' => 'sometimes|nullable|exists:tenant.categories,id',
            'description' => 'sometimes|nullable|string',
            'slug' => [
				'sometimes', 'nullable', 'string', 'max:191',
				Rule::unique('products', 'slug')->ignore($this->route('product')),
            ],
        ];
    }
}
