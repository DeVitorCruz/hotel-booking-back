<?php
  namespace App\Http\Controllers\Api\Admin;

  use App\Http\Controllers\Controller;
  use App\Http\Resources\CategoryResource;
  use App\Models\Category;
  use Illuminate\Http\JsonResponse;
  use Illuminate\Http\Request;

  /**
   * Handles admin approval and rejection of seller-suggested categories.
   * 
   * All endpoints require authentication and the 'admin' role via
   * the EnsureUserIsAdmin middleware applied at the route level.
   */	
  class CategoryApprovalController extends Controller
  {
      /**
       * List all categories pending admin review.
       * 
       * Returns categories in ascending order of submission date
       * so the oldest suggestions are reviewed first (FIFO).
       * 
       * @return JsonResponse 200 with a collection of pending CategoryResource.
       */
      public function index(): JsonResponse
      {
          $categories = Category::with('suggestedBy', 'parent')
              ->where('status', 'pending')
              ->orderBy('created_at', 'asc')
              ->get();

          return response()->json([
              'categories' => CategoryResource::collection($categories),
          ]);
      }

      /**
       * Approve a pending category suggestion.
       * 
       * Sets the category status to 'approved', activates it for public
       * listing, and records which admin performed the approval.
       * Only categories with 'pending' status can be approved.
       * 
       * @param Request  $request The authenticated admin request.
       * @param Category $category The category to approve (route model binding).
       * @return JsonResponse 200 with updated CategoryResource,
       *                      422 if the category is not in pending state.
       */
      public function approve(Request $request, Category $category):  JsonResponse
      {
         if ($category->status !== 'pending') {
             return response()->json([
                 'message' => 'Category is not pending approval.',
             ], 422);
         }

         $category->update([
             'status' => 'approved',
             'is_active' => true,
             'approved_by' => $request->user()->id,
         ]);

         return response()->json([
            'message' => 'Category approved successfully.',
            'category' => new CategoryResource($category),
         ]);
      }
    
      /**
       * Reject a pending category suggestion.
       * 
       * Sets the category status to 'rejected' and keeps it inactive.
       * Records which admin performed the rejection.
       * Only categories with 'pending' status can be rejected.
       * 
       * @param Request  $request The authenticated admin request.
       * @param Category $category The category to reject (route model binding).
       * @return JsonResponse 200 with updated CategoryResource,
       *                      422 if the category is not in pending state.
       */
      public function reject(Request $request, Category $category): JsonResponse 
      {
         if ($category->status !== 'pending') {
             return response()->json([
                 'message' => 'Category is not pending approval.',
             ], 422);
         }

         $category->update([
             'status' => 'rejected',
             'is_active' => false,
             'approved_by' => $request->user()->id,
         ]);

         return response()->json([
             'message' => 'Category rejected.',
             'category' => new CategoryResource($category),
         ]);
     }
 }
