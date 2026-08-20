<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamMembershipController extends Controller
{
    /**
     * انضمام المستخدم إلى الفريق بواسطة الكود.
     */
    public function join(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'join_code' => [
                'required',
                'string',
                'max:20',
            ],
        ]);

        $team = Team::where(
            'join_code',
            strtoupper($validated['join_code'])
        )->first();

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'كود الانضمام غير صحيح.',
                'data' => null,
            ], 404);
        }

        $existingMembership = TeamMembership::where([
            'team_id' => $team->id,
            'user_id' => $request->user()->id,
        ])->first();

        if ($existingMembership) {
            return response()->json([
                'success' => false,
                'message' => 'أنت منضم إلى هذا الفريق مسبقًا.',
                'data' => null,
            ], 409);
        }

        $membership = TeamMembership::create([
            'team_id' => $team->id,
            'user_id' => $request->user()->id,
            'status' => 'active',
            'points_earned' => 0,
            'joined_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم الانضمام إلى الفريق بنجاح.',
            'data' => [
                'team' => $team,
                'membership' => $membership,
            ],
        ], 201);
    }

    /**
     * عرض أعضاء الفريق.
     */
    public function index(Request $request, Team $team): JsonResponse
    {
        if (!$this->canAccessTeam($request, $team)) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية لمشاهدة أعضاء الفريق.',
                'data' => null,
            ], 403);
        }

        $memberships = $team->memberships()
            ->with('user:id,name,email,role,profile_image')
            ->where('status', 'active')
            ->orderByDesc('points_earned')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'تم جلب أعضاء الفريق بنجاح.',
            'data' => [
                'members' => $memberships,
            ],
        ]);
    }

    /**
     * حذف عضو — مدير الفريق فقط.
     */
    public function remove(
        Request $request,
        Team $team,
        User $user
    ): JsonResponse {
        if ($team->admin_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن لمدير الفريق فقط حذف الأعضاء.',
                'data' => null,
            ], 403);
        }

        if ($user->id === $team->admin_id) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف مدير الفريق.',
                'data' => null,
            ], 422);
        }

        $membership = TeamMembership::where([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ])->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم ليس عضوًا في هذا الفريق.',
                'data' => null,
            ], 404);
        }

        $membership->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف العضو من الفريق.',
            'data' => null,
        ]);
    }

    /**
     * خروج المستخدم الحالي من الفريق.
     */
    public function leave(Request $request, Team $team): JsonResponse
    {
        if ($team->admin_id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'مدير الفريق لا يستطيع مغادرة فريقه.',
                'data' => null,
            ], 422);
        }

        $membership = TeamMembership::where([
            'team_id' => $team->id,
            'user_id' => $request->user()->id,
        ])->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'أنت لست عضوًا في هذا الفريق.',
                'data' => null,
            ], 404);
        }

        $membership->delete();

        return response()->json([
            'success' => true,
            'message' => 'تمت مغادرة الفريق بنجاح.',
            'data' => null,
        ]);
    }

    private function canAccessTeam(Request $request, Team $team): bool
    {
        if ($team->admin_id === $request->user()->id) {
            return true;
        }

        return $team->memberships()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->exists();
    }
}
