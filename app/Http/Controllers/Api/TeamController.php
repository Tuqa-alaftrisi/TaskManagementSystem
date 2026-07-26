<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /**
     * عرض الفرق التي يديرها المستخدم أو هو عضو فيها.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $teams = Team::query()
            ->with('admin:id,name,email,profile_image')
            ->withCount([
                'memberships as members_count' => function ($query) {
                    $query->where('status', 'active');
                },
            ])
            ->where(function ($query) use ($userId) {
                $query->where('admin_id', $userId)
                    ->orWhereHas('memberships', function ($membershipQuery) use ($userId) {
                        $membershipQuery
                            ->where('user_id', $userId)
                            ->where('status', 'active');
                    });
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'teams' => $teams,
            ],
        ]);
    }

    /**
     * إنشاء فريق — للمدير فقط.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'team_name' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $user = $request->user();

        $team = DB::transaction(function () use ($user, $validated) {
            $team = Team::create([
                'admin_id' => $user->id,
                'team_name' => $validated['team_name'],
                'join_code' => $this->generateUniqueJoinCode(),
            ]);

            TeamMembership::create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'status' => 'active',
                'points_earned' => 0,
                'joined_at' => now(),
            ]);

            return $team;
        });

        $team->load('admin:id,name,email,profile_image');

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الفريق بنجاح.',
            'data' => [
                'team' => $team,
            ],
        ], 201);
    }

    /**
     * عرض فريق محدد.
     */
    public function show(Request $request, Team $team): JsonResponse
    {
        if (!$this->canAccessTeam($request, $team)) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية لمشاهدة هذا الفريق.',
            ], 403);
        }

        $team->load([
            'admin:id,name,email,profile_image',
            'members' => function ($query) {
                $query
                    ->wherePivot('status', 'active')
                    ->select([
                        'users.id',
                        'users.name',
                        'users.email',
                        'users.role',
                        'users.profile_image',
                    ]);
            },
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'team' => $team,
            ],
        ]);
    }

    /**
     * تعديل الفريق — مدير الفريق فقط.
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        if ($team->admin_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن لمدير هذا الفريق فقط تعديله.',
            ], 403);
        }

        $validated = $request->validate([
            'team_name' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $team->update([
            'team_name' => $validated['team_name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل الفريق بنجاح.',
            'data' => [
                'team' => $team->fresh(),
            ],
        ]);
    }

    /**
     * حذف الفريق — مدير الفريق فقط.
     */
    public function destroy(Request $request, Team $team): JsonResponse
    {
        if ($team->admin_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن لمدير هذا الفريق فقط حذفه.',
            ], 403);
        }

        $team->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الفريق بنجاح.',
        ]);
    }

    /**
     * إنشاء كود انضمام فريد.
     */
    private function generateUniqueJoinCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Team::where('join_code', $code)->exists());

        return $code;
    }

    /**
     * التحقق من قدرة المستخدم على مشاهدة الفريق.
     */
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
