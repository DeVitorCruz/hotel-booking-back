<?php
  namespace App\Http\Controllers\Api;

  use App\Http\Controllers\Controller;
  use App\Http\Resources\ProductResource;
  use App\Models\Product;
  use Illuminate\Http\JsonResponse;
  use Illuminate\Http\Request;

  class ProductController extends Controller 
  {
      /** Public product listing with filters */
      public function index(Request $request): JsonResponse
      {
         $query = Product::with('variants', 'category', 'seller');
         // Filter by category
         if ($request->has('category_id')) {
             $query->where('category_id', $request->category_id);
         }

         // Filter by category slug
         if ($request->has('category')) {
             $query->whereHas('category', function ($q) use ($request) {
                 $q->where('slug', $request->category);
             });
         }

         // Filter by price range
         if ($request->has('min_price')) {
             $query->whereHas('variants', function ($q) use ($request) {
                 $q->where('price', '>=', $request->min_price);
             });
         }

         if ($request->has('max_price')) {
             $query->whereHas('variants', function ($q) use ($request) {
                  $q->where('price', '<=', $request->max_price);               
             });
         }

         // Sort
         $sortBy = $request->get('sort_by', 'created_at');
         $sortDir = $request->get('sort_dir', 'desc');
         $allowedSorts = ['created_at', 'name'];
         if (in_array($sortBy, $allowedSorts)) {
             $query->orderBy($sortBy, $sortDir);
         }
 
         $products = $query->paginate($request->get('per_page', 15));
         return response()->json([
             'products' => ProductResource::collection($products),
             'pagination' => [
                 'total' => $products->total(),
                 'per_page' => $products->perPage(),
                 'current_page' => $products->currentPage(),
                 'last_page' => $products->lastPage(),
             ],
         ]);
      }

      /** Public single product */
      public function show(Product $product): JsonResponse
      {
         $product->load('variants.attributeValues.attribute', 'category', 'seller');
 
         return response()->json([
             'product' => new ProductResource($product),
         ]);
      }

      /** Authenticated seller's own products */
      public function sellerProducts(Request $request): JsonResponse
      {
          $seller = $request->user()->seller;

          if (!$seller) {
              return response()->json([
                  'message' => 'You do not have a seller profile.',
              ], 403);
          }

          $products = Product::with('variants', 'category')
              ->where('seller_id', $seller->id)
              ->orderBy('created_at', 'desc')
              ->paginate($request->get('per_page', 15));

          return response()->json([
              'products' => ProductResource::collection($products),
              'pagination' => [
                  'total' => $products->total(),
                  'per_page' => $products->perPage(),
                  'current_page' => $products->currentPage(),
                  'last_page' => $products->lastPage(),
              ],
          ]);
      }
}
