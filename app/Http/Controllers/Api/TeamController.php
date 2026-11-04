<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /**
     * GET /teams
     * - owner/admin: all teams
     * - seller: their store teams
     * - others: teams they are members of
     * 
     * @param  Request $request user request
     * @return JsonResponse 200 ok, get teams list
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Team::with(['owner:id,name', 'seller:id,store_name']);

        if ($user->hasAnyRole(['owner', 'admin'])) {
            // Full view
        } elseif ($user->hasRole('owner')) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhere('seller_id', $user->seller->id);
            });
        } else {
            // Member view - teams they belong to 
            $teamIds = TeamMember::where('user_id', $user->id)
                ->where('status', 'active')->pluck('team_id');
            
            $query->whereIn('id', $teamIds);
        }

        return response()->json($query->where('is_active', true)->get());
    }

    /**
     * POST /teams - create a team
     * 
     * @param  Request     $request request user
     * @return JsonRespone 201 created, get owner/memberships
     *                     403 User can access only its store.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'seller_id' => 'nullable|exists:sellers,id',
        ]);

        // Seller can only create teams for their own store

        if ($user->hasRole('seller') && isset($data['seller_id'])) {
            if ($user->seller?->id != $data['seller_id']) {
                return response()->json([
                    'message' => 'You can only create teams for your own store.',
                ], 403);
            }
        }

        $data['owner_id'] = $user->id;
        $data['slug'] = Str::slug($data['name']) . '-' . uniqid();

        $team = Team::create($data);

        // Auto-add creator as leader

        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => 'leader',
            'joined_at' => now(),
        ]);

        return response()->json(
            $team->load(['owner:id,name', 'memberships'])
        , 201);
    }

    /**
     * GET /teams/{team} - view team with members
     * 
     * @param  Request      $request user request
     * @param  Team         $team team to be consulted   
     * @return JsonResponse 200 ok, view team 
     *                      403 Unauthorized, user can't access team
     */
    public function show(Request $request, Team $team): JsonResponse 
    {
        $user = $request->user();

        if (!$this->canAccess($user, $team)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403); 
        }

        return response()->json($team->load([
            'owner:id,name',
            'seller:id,store_name',
            'members:id,name,email',
        ]));
    }

    /**
     * PATCH /teams/{team} - update team info
     * 
     * @param  Request      $request user request
     * @param  Team         $team team to update
     * @return JsonResponse 200 ok, team successfully updated
     *                      403 Unauthorized, user can't update the team  
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();

        if (!$this->canManage($user, $team)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:191',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']) . '-' . uniqid();
        }

        $team->update($data);

        return response()->json($team->fresh());
    }

    /**
     * DELETE /teams/{team} - deactivate team
     * 
     * @param  Request      $request user request
     * @param  Team         $team team to be deleted
     * @return JsonResponse 200 ok, succesfully deleted
     *                      403 Unauthorized, user aren't allowed to manage team
     */
    public function destroy(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();

        if (!$this->canManage($user, $team)) {
            response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }
    
        $team->update(['is_active' => false]); 

        return response()->json([
            'message' => 'Team deactivated.',
        ]);
    }

    /**
     * POST /teams/{team}/members - add a member
     * 
     * @param  Request       $request user request
     * @param  Team          $team team member to be posted
     * @return JsonResponse  201 created, team members successfully added
     *                       403 Unauthorized, user aren't allowed to manage this team
     */
    public function addMember(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();

        if (!$this->canManage($user, $team)) {
            return response()->json([
                'message' => 'Unauthozied.',
            ], 403);
        }

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|in:leader,member'
        ]);

        // Only one leader per team

        if (($data['role'] ?? 'member') === 'leader') {
            TeamMember::where('team_id', $team->id)
                ->where('role', 'leader')
                ->where('status', 'active')
                ->update(['role' => 'member']);
        }

        $member = TeamMember::updateOrCreate(
            [
                'team_id' => $team->id, 
                'user_id' => $data['user_id']
            ],
            [
                'role' => $data['role'] ?? 'member',
                'status' => 'active',
                'joined_at' => now(),
                'left_at' => null,
            ]
        );

        return response()->json([
            'message' => 'Member added to team.',
            'member' => $member->load('user:id,name,email'),
        ], 201);
    }

    /**
     * DELETE /teams/{team}/members/{user} - remove a member
     * 
     * @param  Request      $request user request
     * @param  Team         $team team member to be fired
     * @return JsonResponse 200 ok, team members fired successfully
     *                      403 Unauthorized, only allowed user can manage team member
     */
    public function removeMember(Request $request, Team $team, User $user): JsonResponse
    {
        $requester = $request->user();

        if (!$this->canManage($requester, $team)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        TeamMember::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->update([
                'status' => 'left',
                'left_at' => now(),
            ]);

        return response()->json(['message' => 'Member removed from team.']);
    }

    /**
     * Check if user can access the team
     * 
     * @param  User $user user to be checked
     * @param  Team $team team to be checked
     * @return bool       true if access allowed or false if access not allowed
     */
    private function canAccess(User $user, Team $team): bool
    {
        if ($user->hasAnyRole(['owner', 'admin'])) return true;
        if ($team->owner_id === $user->id) return true;
        if ($team->seller && $team->seller_id === $user->seller->id) return true;

        return TeamMember::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')->exists();
    }

    /**
     * Check if user can manage the team
     * 
     * @param  User $user user to manage
     * @param  Team $team team to be managed
     * @return bool true if can manage false if cannot 
     */
    private function canManage(User $user, Team $team): bool
    {
        if ($user->hasAnyRole([['owner', 'admin']])) return true;
        if ($team->owner_id === $user->id) return true;

        return TeamMember::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('role', 'leader')
            ->where('status', 'active')->exists();
    }
}
