<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use App\Models\Blog;

class BlogController extends Controller
{
    /**
     * GET /blogs - public listing (published only)
     * Supports ?category= and ?search= filters.
     * 
     * @param  Request      $request the author's blogs
     * @return JsonResponse 200 view of the author's blogs
     */
    public function index(Request $request): JsonResponse
    {
        $blogs = Blog::with('author:id,name')
            ->where('status', 'published')
            ->when($request->search, fn($q, $s) => 
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('excerpt', 'like', "%{$s}%"))
            ->latest('published_at')
            ->paginate(12);

        return response()->json($blogs);
    }

    /**
     * GET /blogs/{blog} - public single post (published only)
     * 
     * @param Blog          $blog single blog
     * @return JsonResponse 200 get author's post
     *                      404 if the post isn't published
     */
    public function show(Blog $blog): JsonResponse
    {
        if (!$blog->isPublished()) {
            return response()->json([
                'message' => 'Post not found.'
            ], 404);
        }

        return response()->json($blog->load('author:id,name'));
    }

    /**
     * POST /blogs - create draft (editor/admin/owner)
     * 
     * @param Request $request the user's request to create a post
     * @return JsonResponse 201 store the post and retrieve success answer
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'excerpt' => 'nullable|string|max:191',
            'body' => 'required|string',
            'cover_image' => 'nullable|image|max:4096',
            'status' => 'nullable|in:draft,published',
        ]);

        $data['user_id'] = $request->user()->id;
        $data['slug'] = Str::slug($data['title']);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')
                ->store('blogs', 'public');
        }
    
        if (($data['status'] ?? 'draft') === 'published') {
            $data['published_at'] = now();
        }

        return response()->json(Blog::create($data), 201);
    }

    /**
     * PATCH /blogs/{blog} - update own post (editor) or any post (admin/owner)
     * 
     * @param  Request      $request user's request to update a post
     * @param  Blog         $blog    single blog to be updated
     * @return JsonResponse 200      retrieve successfully update message 
     *                      403      if the user is unauthorized. 
     */
    public function update(Request $request, Blog $blog): JsonResponse
    {
        
        $user = $request->user();

        // Editors can only edit their own posts
        if ($user->hasRole('editor') && $blog->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized.'
            ], 403);
        }

        $data = $request->validate([
            'title' => 'sometimes|string|max:191',
            'excerpt' => 'nullable|string|max:191',
            'body' => 'sometimes|string',
            'cover_image' => 'nullable|image|max:4096',
            'status' => 'nullable|in:draft,published',
        ]);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')
                ->store('blogs', 'public');
        }

        // Set published_at when first publishind
        if (isset($data['status']) && $data['status'] === 'published' && !$data['published_at']) {
            $data['published_at'] = now();
        }

        $blog->update($data);

        return response()->json($blog->fresh('author'));
    }

    /**
     * DELETE /blogs/{blog} - delete own post (editor) or any (admin/awner)
     * 
     * @param  Request      $request user request to delete a post
     * @param  Blog         $blog blog post to be deleted by the user
     * @return JsonResponse 204 delete successfully the post
     *                      403      
     */
    public function destroy(Request $request, Blog $blog): JsonReponse
    {
        $user = $request->user();

        if ($user->hasRole('editor') && $blog->user_id !== $request->id) {
            return response()->json([
                'message' => 'Unauthorized.' 
            ], 403);
        }

        $blog->delete();

        return response()->json(null, 204);
    }


    /**
     * GET /admin/blogs - all posts including drafts (admin/owner)
     * 
     * @param  Request      $request privileged (admin/owner) user requesting all posts
     * @return JsonResponse 200 view all the privileged author's posts
     */
    public function adminIndex(Request $request)
    {
        $blogs = Blog::with('author:id,name')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()->paginated(20);

        return response()->json($blogs);
    }
}
