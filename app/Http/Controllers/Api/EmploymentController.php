<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmploymentController extends Controller
{
    /**
     * GET /employments
     * - owner/admin: see all employments
     * - seller: see only their store employments
     * - employee: see own employments 
     * 
     * @param  Request      $request request from the user
     * @return JsonResponse 200 all employments
     */
    public function index(Request $request): JsonResponse 
    {
        $user = $request->user();
        $query = Employment::with([
            'employer:id,name,email',
            'employee:id,name,email',
            'seller:id,store_name,slug',
        ]);

        if ($user->hasAnyRole(['owner', 'admin'])) {
            // Full view - no filter
        } elseif($user->hasRole('seller') && $user->seller) {
            $query->where('seller_id', $user->seller->id);
        } else {
            $query->where('employee_id', $user->id);
        }

        return response()->json(
            $query->latest()->paginate(20)
        );
    }

    /**
     * POST /employments
     * Hire a user.
     * - owner/admin: platform-level hire (editor, employee)
     * - seller (marketplace): store-level hire (employee only)
     * 
     * @param  Request      $request editor/employee to be stored
     * @return JsonResponse 201 editor/employee successfully createdq
     */
    public function store(Request $request): JsonResponse
    {
        $employer = $request->user();

        $data = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'role_name' => 'required|in:editor,employee',
            'seller_id' => 'nullable|exists:sellers,id',
            'notes' => 'nullable|string|max:500',
        ]);

        // Prevent self-hiring
        if ($data['employee_id'] == $employer->id) {
            return response()->json([
                'message' => 'You cannot hire yourself.',
            ], 422);
        }

        // Seller score rules
        if ($employer->hasRole('seller')) {
            if (!$employer->isMarketplace()) {
                return response()->json([
                    'message' => 'Your store is not enabled for marketplace hiring. Contact an admin.',
                ], 403);
            } 
            // Sellers can only hire employees, not editors

            $data['role_name'] = 'employee';
            $data['seller_id'] = $employer->seller->id;
        }

        // Check for existing active employment
        $exists = Employment::where('employer_id', $employer->id)
            ->where('employee_id', $data['employee_id'])
            ->where('seller_id', $data['seller_id'] ?? null)
            ->where('status', 'active')->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This user is already employed in this context.',
            ], 409);
        }

        $data['employer_id'] = $employer->id;
        $data['hired_at'] = now();

        $employment = Employment::create($data);

        // Assign Spatie role to the hired user
        $employee = User::findOrFail($data['employee_id']);

        $employee->assignRole($data['role_name']);

        return response()->json([
            'message' => 'User hired successfully.',
            'employment' => $employment->load([
                'employer:id,name,email',
                'employee:id,name,email',
                'seller:id,store_name',
            ]),
        ], 201);
    } 

    /**
     * GET /employments/{employment}
     * View a single employment record.
     * 
     * @param  Request      $request the user request
     * @param  Employment   $employment the employment to be got
     * @return JsonResponse 200 ok, get employement 
     */
    public function show(Request $request, Employment $employment): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['owner', 'admin']) 
            && $employment->employer_id !== $user->id 
            && $employment->employee_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json($employment->load([
            'employer:id,name,email',
            'employee:id,name,email',
            'seller:id,store_name',
        ]));
    }

    /**
     * PATCH /employments/{employment}/suspend
     * Suspend an active employment.
     * 
     * @param  Request      $request the user request
     * @param  Employment   $employment the employment to be updated
     * @return JsonResponse 200 ok, update the employment
     *                      403 Anauthorized uncredentialed user
     *                      422 active only inactive employments
     */
    public function suspend(Request $request, Employment $employment): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['owner', 'admin'])
            && $employment->employer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($employment->status !== 'active') {
            return response()->json([
                'message' => 'Only active employments can be suspended.',
            ], 422);
        }

        $employment->update(['status' => 'suspended']);

        return response()->json([
            'message' => 'Employment suspended.',
            'employment' => $employment->fresh(),
        ]);
    }

    /**
     * DELETE /employments/{employment}
     * Terminate an employment - removes role if no other active employments exist.
     * 
     * @param  Request      $request user request
     * @param  Employment   $employment employment to be deleted
     * @return JsonResponse 200 ok, employment successfully deleted
     *                      403 unauthorized, only credential user allowed
     */
    public function destroy(Request $request, Employment $employment): JsonResponse
    {
        $user = $request->user();

        if(!$user->hasAnyRole(['owner', 'admin'])
            && $employment->employer_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $employment->update([
            'status' => 'terminated',
            'fired_at' => now(),
        ]);

        // Remove role only if no other active employment exist
        $employee = $employment->employee;
        $hasOtherActive = Employment::where('employee_id', $employee->id)
            ->where('id', '!=', $employment->id)
            ->where('status', 'active')->exists();

        if (!$hasOtherActive) {
            $employee->removeRole($employment->role_name);

            // Ensure customer role as fallback
            if (!$employee->hasRole('customer')) {
                $employee->assignRole('customer');
            }
        }

        return response()->json([
            'message' => 'Employment terminated. Role remove if no other active employments.',
        ]);
    }
}
