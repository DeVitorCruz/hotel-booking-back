<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FAQ;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FAQController extends Controller
{
    /**
     * GET /faqs - public listing (active only)
     * Supports ?category= filter.
     * 
     * @param Request       $request user's request to see all the faq
     * @return JsonResponse 200 view successfully all the faq of the user 
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            FAQ::where('active', true)
                ->when($request->category, fn($q, $c) => $q->where('category', $c))
                ->orderBy('order')->get()
        );
    }

    /**
     * POST /faqs - create (admin/owner/editor)
     * 
     * @param  Request      $request priviled user request to create a faq
     * @return JsonResponse 201 created the successfully the faq
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => 'required|string',
            'answer' => 'required|string',
            'category' => 'nullable|string|max:80',
            'order' => 'nullable|integer|min:0',
            'active' => 'nullable|boolean'
        ]);

        return response()->json(FAQ::create($data), 201);
    }

    /**
     * PATCH /faqs/{faq} - update (admin/owner/editor)
     * 
     * @param  Request      $request privilede user update request
     * @param  FAQ          $faq     faq item to be updated
     * @return JsonResponse 200 ok data delivered 
     */
    public function update(Request $request, FAQ $faq): JsonResponse
    {
        $faq->update($request->validate([
            'question' => 'sometimes|string',
            'answer' => 'sometimes|string',
            'category' => 'nullable|string|max:80',
            'order' => 'nullable|integer|min:0',
            'active' => 'nullable|boolean',
        ]));

        return response()->json($faq);
    }

    /**
     * DELET /faqs/{faq} - delete (admin/owner)
     * 
     * @param  FAQ          $faq faq item to be deleted
     * @return JsonRespones 204 request delivered without content result
     */
    public function destroy(FAQ $faq): JsonResponse
    {
        $faq->delete();

        return response()->json(null, 204);
    }
}
