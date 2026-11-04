<?php
  namespace App\Http\Controllers\Api;

  use App\Http\Controllers\Controller;
  use App\Http\Requests\StoreProductRequest;
  use App\Http\Requests\UpdateProductRequest;
  use App\Http\Resources\ProductResource;
  use App\Models\Product;
  use App\Models\Attribute;
  use App\Models\AttributeValue;
  use Illuminate\Http\JsonResponse;
  use Illuminate\Support\Facades\DB;
  use Illuminate\Support\Facades\Storage;
  use Illuminate\Support\Str;

  /**
   * Handles admin approval and rejection of seller-suggested categories.
   * 
   * All endpoints in this controller require the user to be authenticated
   * Via Sanctum and to have an active seller profile.
   */	
  class SellerProductController extends Controller
  {
	  /**
	   * Create a new product with its variants and attributes.
	   * 
	   * This method handles the full product creation flow in a single
	   * database transaction. If any step fails, all changes are rolled back
	   * to ensure data integrity.
	   * 
	   * Flow:
	   *  1. Create the base product record linked to the authenticated seller.
	   *  2. For each variant, handle optional image upload and create the variant.
	   *  3. For each attribute in a variant, find or create the attribute and	
	   *     its value, then attach it to the variant via pivot table.
	   * 
	   * @param StoreProductRequest $request Validated request containing product
	   *                                      data, variants and their attributes.      
	   * @return JsonResponse 201 with the created product resource on success,
	   *                      500 with error message on failure. 
	   */
      public function store(StoreProductRequest $request): JsonResponse
      {
         try {
             DB::beginTransaction();

             $product = Product::create([
                 'seller_id' => $request->user()->seller->id,
                 'category_id' => $request->validated('category_id'),
                 'name' => $request->validated('name'),
                 'slug' => Str::slug($request->validated('slug') ?? $request->validated('name')),
                 'description' => $request->validated('description'),
             ]);

             foreach ($request->validated('variants') as $index => $variantData) {
                $imagePath = null;
                if ($request->hasFile("variants.$index.image_path")) {
                    $imagePath = $request->file("variants.$index.image_path")
                        ->store('products', 'public');
                }

                $variant = $product->variants()->create([
                    'sku' => $variantData['sku'],
                    'price' => $variantData['price'],
                    'stock_quantity' => $variantData['stock_quantity'],
                    'image_path' => $imagePath,
                ]);

                if (!empty($variantData['attributes'])) {
                    foreach ($variantData['attributes'] as $attributeData) {
                        $attribute = Attribute::firstOrCreate([
                            'name' => $attributeData['name'],
                        ]);
    
                        $attributeValue = AttributeValue::firstOrCreate([
                            'attribute_id' => $attribute->id,
                            'value' => $attributeData['value'],
                        ]);
    
                        $variant->attributeValues()->attach($attributeValue->id);
                    }
                }
             }

             DB::commit();

             $product->load('variants.attributeValues.attribute', 'category', 'seller');

             return response()->json([
                'message' => 'Product created successfully.',
                'product' => new ProductResource($product),
             ], 201);

          } catch (\Exception $e) {
              DB::rollBack();
              return response()->json([
                  'message' => 'Failed to create product.',
                  'error' => $e->getMessage(),
              ], 500);
          }
      }
      
      /**
       * Update the basic information of an existing product.
       * 
       * Only updates the product's top-level fields (name, slug, description,
       * category). Varient management is handled separately.]
       * Authorization is enforced by UpdateProductRequest - only the seller
       * who owns the product can update it.
       * 
       * If the name is updated but no slug is provided, a new slug is
       * automatically generated from the updated name.
       * 
       * @param UpdatedProductRequest $request Validated request with fields to update.
       * @param Product               $product The product to update (route model binding).
       * @return JsonResponse 200 with the updated product resource.
       */
      public function update(UpdateProductRequest $request, Product $product): JsonResponse 
      {
		  $data = $request->validated();
		  
		  if (isset($data['name']) && !isset($data['slug'])) {
			  $data['slug'] = Str::slug($data['name']);
		  } elseif (isset($data['slug'])) {
			  $data['slug'] = Str::slug($data['slug']);
		  }
		  
		  $product->update($data);
		  $product->load('variants.attributeValues.attribute', 'category', 'seller');
		  
		  return response()->json([
				'message' => 'Product updated successfully.',
				'product' => new ProductResource($product),
		  ]);
	  }
	  
	  /**
	   * Delete a product and clean up its associated files.
	   * 
	   * Before deleting the product record, this method removes any stored
	   * variant images from the public disk to prevent orphaned files.
	   * Only the seller who owns the product can delete it.
	   * 
	   * @param Product $product The product to delete (rout model binding).
	   * @return JsonResponse 200 on success, 403 if the seller does not own the product.
	   */
	  public function destroy(Product $product): JsonResponse
	  {
	     // Ensure the seller owns this product
	     $seller = auth()->user()->seller;
		
		 if (!$seller || $product->seller_id !== $seller->id) {
			 return response()->json([
				'message' => 'Unauthorized.',
			 ], 403);
		 }
		 
		 // Delete variant images from storage
		 foreach ($product->variants as $variant) {
			  if ($variant->image_path) {
				  Storage::disk('public')->delete($variant->image_path);	
			  }
	     }
	     
	     $product->delete();
	     
	     return response()->json([
			 'message' => 'Product deleted successfully.',
	     ]);
	  }
 }
