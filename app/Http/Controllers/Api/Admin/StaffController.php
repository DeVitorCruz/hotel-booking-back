<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Back-office accounts. Only an owner can create or promote another owner,
 * and the last owner can never be removed or demoted.
 */
class StaffController extends Controller
{
    /** GET /admin/staff */
    public function index(): JsonResponse
    {
        return response()->json(
            User::role(User::STAFF_ROLES)->with('roles:id,name')->orderBy('name')->get()
                ->map(fn (User $u) => $this->present($u))
        );
    }

    /** POST /admin/staff { name, email, password, role } */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:191'],
            'role' => ['required', Rule::in(User::STAFF_ROLES)],
        ]);
        $this->assertCanGrant($request->user(), $data['role']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([$data['role']]);

        return response()->json($this->present($user->load('roles:id,name')), 201);
    }

    /** PATCH /admin/staff/{user} { name?, role?, password? } */
    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->isStaff(), 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'role' => ['sometimes', Rule::in(User::STAFF_ROLES)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:191'],
        ]);

        if (isset($data['role']) && ! $user->hasRole($data['role'])) {
            $this->assertCanGrant($request->user(), $data['role']);
            if ($user->hasRole('owner')) {
                $this->assertCanGrant($request->user(), 'owner');
                $this->assertNotLastOwner($user);
            }
            $user->syncRoles([$data['role']]);
        }
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()?->id)->delete();
        }
        $user->save();

        return response()->json($this->present($user->load('roles:id,name')));
    }

    /** DELETE /admin/staff/{user} — removes back-office access (the account becomes a guest). */
    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($user->isStaff(), 404);

        if ($user->is($request->user())) {
            throw new BookingException('You cannot remove your own access.', 'self', 409);
        }
        if ($user->hasRole('owner')) {
            $this->assertCanGrant($request->user(), 'owner');
            $this->assertNotLastOwner($user);
        }

        $user->syncRoles(['guest']);
        $user->tokens()->delete();

        return response()->json(null, 204);
    }

    private function assertCanGrant(User $actor, string $role): void
    {
        if ($role === 'owner' && ! $actor->hasRole('owner')) {
            throw new BookingException('Only an owner can manage owner accounts.', 'forbidden_role', 403);
        }
    }

    private function assertNotLastOwner(User $user): void
    {
        if (User::role('owner')->count() <= 1 && $user->hasRole('owner')) {
            throw new BookingException('The hotel must keep at least one owner.', 'last_owner', 409);
        }
    }

    private function present(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->roles->first()?->name,
            'created_at' => $u->created_at?->toIso8601String(),
        ];
    }
}
