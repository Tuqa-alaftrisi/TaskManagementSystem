<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JoinRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamManagementController extends Controller
{
    /**
     * إضافة عضو إلى الفريق مباشرة (بدون كود انضمام).
     * متاح لمدير الفريق فقط.
     */
    public function addMember(Request $request, Team $team): JsonResponse
    {
        if ($team->admin_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن لمدير هذا الفريق فقط إضافة أعضاء.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        // البحث عن المستخدم أو إنشاؤه
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'name' => trim($validated['name']),
                'email' => $email,
                'password' => bcrypt('password123'),
                'role' => 'member',
                'email_verified_at' => now(),
            ]);
        }

        // التحقق من العضوية المسبقة
        $existingMembership = TeamMembership::where([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ])->first();

        if ($existingMembership) {
            return response()->json([
                'success' => false,
                'message' => 'هذا المستخدم عضو في الفريق بالفعل.',
                'errors' => [
                    'email' => ['هذا المستخدم عضو في الفريق بالفعل.'],
                ],
            ], 409);
        }

        // إضافة العضو
        $membership = TeamMembership::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'status' => 'active',
            'points_earned' => 0,
            'joined_at' => now(),
        ]);

        $membership->load('user:id,name,email,role,profile_image');

        Notification::createNotification(
            $user->id,
            "You have been added to the team {$team->name}.",
            Notification::TYPE_ACCEPTED,
            'Added to Team',
            '/teams/' . $team->id,
            [
                'team_id' => $team->id,
                'added_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة العضو إلى الفريق بنجاح.',
            'data' => [
                'membership' => [
                    'id' => $membership->id,
                    'team_id' => $membership->team_id,
                    'user_id' => $membership->user_id,
                    'status' => $membership->status,
                    'points_earned' => $membership->points_earned,
                    'joined_at' => $membership->joined_at,
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_image' => $user->profile_image
                        ? asset('storage/' . $user->profile_image)
                        : null,
                ],
            ],
        ], 201);
    }

    /**
     * مراجعة طلب انضمام (قبول أو رفض).
     * متاح لمدير الفريق فقط.
     */
    public function reviewJoinRequest(
        Request $request,
        Team $team,
        JoinRequest $joinRequest
    ): JsonResponse {
        if ($team->admin_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن لمدير هذا الفريق فقط مراجعة طلبات الانضمام.',
            ], 403);
        }

        if ($joinRequest->team_id !== $team->id) {
            return response()->json([
                'success' => false,
                'message' => 'طلب الانضمام غير موجود ضمن هذا الفريق.',
            ], 404);
        }

        if ($joinRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'تمت مراجعة هذا الطلب مسبقًا.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(['approved', 'rejected']),
            ],
        ]);

        DB::transaction(function () use ($joinRequest, $validated, $request, $team) {
            $joinRequest->update([
                'status' => $validated['status'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            // إذا تم القبول، أضف العضو إلى الفريق
            if ($validated['status'] === 'approved') {
                $existingMembership = TeamMembership::where([
                    'team_id' => $team->id,
                    'user_id' => $joinRequest->user_id,
                ])->first();

                if (!$existingMembership) {
                    TeamMembership::create([
                        'team_id' => $team->id,
                        'user_id' => $joinRequest->user_id,
                        'status' => 'active',
                        'points_earned' => 0,
                        'joined_at' => now(),
                    ]);
                }
            }
        });

        if ($validated['status'] === 'approved') {

            Notification::createNotification(
                $joinRequest->user_id,
                "Your request to join {$team->name} has been approved.",
                Notification::TYPE_ACCEPTED,
                'Join Request Approved',
                '/teams/' . $team->id,
                [
                    'team_id' => $team->id,
                    'join_request_id' => $joinRequest->id,
                    'reviewed_by' => $request->user()->id,
                ]
            );
        } else {

            Notification::createNotification(
                $joinRequest->user_id,
                "Your request to join {$team->name} has been rejected.",
                Notification::TYPE_REJECTED,
                'Join Request Rejected',
                '/teams/' . $team->id,
                [
                    'team_id' => $team->id,
                    'join_request_id' => $joinRequest->id,
                    'reviewed_by' => $request->user()->id,
                ]
            );
        }

        $message = $validated['status'] === 'approved'
            ? 'تم قبول الطلب وإضافة المستخدم إلى الفريق.'
            : 'تم رفض طلب الانضمام.';

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * البحث عن مستخدمين لإرسال دعوة لهم.
     * متاح لمدير الفريق فقط.
     */
    public function searchUsers(Request $request, Team $team): JsonResponse
    {
        if ($team->admin_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن لمدير هذا الفريق فقط البحث عن مستخدمين.',
            ], 403);
        }

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $query = trim($validated['q']);

        // البحث عن المستخدمين
        $users = User::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%");
        })
            ->where('id', '!=', $request->user()->id) // استبعاد المدير نفسه
            ->select(['id', 'name', 'email', 'role', 'profile_image'])
            ->limit(20)
            ->get();

        // تحديد علاقة كل مستخدم بالفريق
        $result = $users->map(function ($user) use ($team) {
            $relation = $this->getUserTeamRelation($user->id, $team->id);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->profile_image
                    ? asset('storage/' . $user->profile_image)
                    : null,
                'team_relation' => $relation,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $result,
            ],
        ]);
    }

    /**
     * إرسال دعوة انضمام إلى مستخدم.
     * متاح لمدير الفريق فقط.
     */
    public function sendInvitation(Request $request, Team $team): JsonResponse
    {
        if ($team->admin_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن لمدير هذا الفريق فقط إرسال الدعوات.',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => [
                'required',
                'exists:users,id',
            ],
        ]);

        $userId = $validated['user_id'];

        // التحقق من العضوية المسبقة
        $existingMembership = TeamMembership::where([
            'team_id' => $team->id,
            'user_id' => $userId,
        ])->first();

        if ($existingMembership) {
            return response()->json([
                'success' => false,
                'message' => 'هذا المستخدم عضو في الفريق بالفعل.',
                'errors' => [
                    'search' => ['هذا المستخدم عضو في الفريق بالفعل.'],
                ],
            ], 409);
        }

        // التحقق من وجود دعوة معلقة
        $existingInvitation = TeamInvitation::where([
            'team_id' => $team->id,
            'invited_user_id' => $userId,
            'status' => 'pending',
        ])->first();

        if ($existingInvitation) {
            return response()->json([
                'success' => false,
                'message' => 'تم إرسال دعوة معلقة لهذا المستخدم بالفعل.',
                'errors' => [
                    'search' => ['تم إرسال دعوة معلقة لهذا المستخدم بالفعل.'],
                ],
            ], 409);
        }

        // التحقق من وجود طلب انضمام معلق
        $existingJoinRequest = JoinRequest::where([
            'team_id' => $team->id,
            'user_id' => $userId,
            'status' => 'pending',
        ])->first();

        if ($existingJoinRequest) {
            return response()->json([
                'success' => false,
                'message' => 'لدى المستخدم طلب انضمام معلق؛ يمكنك قبوله من قائمة الطلبات الواردة.',
                'errors' => [
                    'search' => ['لدى المستخدم طلب انضمام معلق؛ يمكنك قبوله من قائمة الطلبات الواردة.'],
                ],
            ], 409);
        }

        // إنشاء الدعوة
        $invitation = TeamInvitation::create([
            'team_id' => $team->id,
            'invited_user_id' => $userId,
            'invited_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        Notification::createNotification(
            $userId,
            "You have been invited to join the team {$team->name}.",
            Notification::TYPE_INVITE,
            'Team Invitation',
            '/teams/' . $team->id,
            [
                'team_id' => $team->id,
                'invitation_id' => $invitation->id,
                'invited_by' => $request->user()->id,
            ]
        );

        $invitation->load('invitedUser:id,name,email');

        return response()->json([
            'success' => true,
            'message' => "تم إرسال دعوة الانضمام إلى {$invitation->invitedUser->name}.",
            'data' => [
                'invitation' => [
                    'id' => $invitation->id,
                    'team_id' => $invitation->team_id,
                    'invited_user_id' => $invitation->invited_user_id,
                    'invited_by' => $invitation->invited_by,
                    'status' => $invitation->status,
                    'sent_at' => $invitation->created_at,
                ],
            ],
        ], 201);
    }

    /**
     * تحديد علاقة المستخدم بالفريق.
     */
    private function getUserTeamRelation(int $userId, int $teamId): string
    {
        // هل هو عضو؟
        $membership = TeamMembership::where([
            'team_id' => $teamId,
            'user_id' => $userId,
        ])->first();

        if ($membership) {
            return 'member';
        }

        // هل لديه دعوة معلقة؟
        $invitation = TeamInvitation::where([
            'team_id' => $teamId,
            'invited_user_id' => $userId,
            'status' => 'pending',
        ])->first();

        if ($invitation) {
            return 'invited';
        }

        // هل لديه طلب انضمام معلق؟
        $joinRequest = JoinRequest::where([
            'team_id' => $teamId,
            'user_id' => $userId,
            'status' => 'pending',
        ])->first();

        if ($joinRequest) {
            return 'join_request';
        }

        // متاح للدعوة
        return 'available';
    }
}
