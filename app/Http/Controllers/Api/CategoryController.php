<?php
  namespace App\Http\Controllers\Api;

  use App\Http\Controllers\Controller;
  use App\Http\Requests\StoreCategoryRequest;
  use App\Http\Resources\CategoryResource;
  use App\Models\Category;
  use Illuminate\Http\JsonResponse;
  use Illuminate\Support\Str;

  class CategoryController extends Controller
  {
    /** List all approved active categories with full tree */
    public function index(): JsonResponse
    {
       $categories = Category::with('allChildren')
           ->whereNull('parent_id')
           ->where('status', 'approved')
           ->where('is_active', true)
           ->get();
 
       return response()->json([
          'categories' => CategoryResource::collection($categories),
       ]);
   }

   /** Seller suggests a new category */
   public function store(StoreCategoryRequest $request): JsonResponse
   {
       $imagePath = null;
       if ($request->hasFile('image')) {
           $imagePath = $request->file('image')->store('categories', 'public');
       }

       $category = Category::create([
           'parent_id' => $request->validated('parent_id'),
           'suggested_by' => auth()->id(),
           'name' => $request->validated('name'),
           'slug' => Str::slug($request->validated('name')),
           'description' => $request->validated('description'),
           'image_path' => $imagePath,
           'status' => 'pending',
           'is_active' => false,
       ]);

       return response()->json([
          'message' => 'Category suggestion submitted for review.',
           'category' => new CategoryResource($category),
], 201);
}

   /** Show a single category with its full subtree */
   public function show(Category $category): JsonResponse
   {
       $category->load('allChildren', 'parent');

       return response()->json([
           'category' => new CategoryResource($category),
       ]);
   }
}
