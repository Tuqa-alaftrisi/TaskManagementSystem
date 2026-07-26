<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    /**
     * عرض جميع مشاريع الفريق.
     */
    public function index(Request $request, Team $team): JsonResponse
    {
        if (!$this->canAccessTeam($request, $team)) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية لمشاهدة مشاريع هذا الفريق.',
            ], 403);
        }

        $projects = $team->projects()
            ->with('creator:id,name,email,profile_image')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'projects' => $projects,
            ],
        ]);
    }

    /**
     * إنشاء مشروع داخل الفريق.
     */
    public function store(Request $request, Team $team): JsonResponse
    {
        if ($team->admin_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن لمدير هذا الفريق فقط إنشاء المشاريع.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'pending',
                    'in_progress',
                    'completed',
                    'cancelled',
                ]),
            ],

            'start_date' => [
                'nullable',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
        ]);

        $project = Project::create([
            'team_id' => $team->id,
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'pending',
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);

        $project->load([
            'team:id,admin_id,team_name,join_code',
            'creator:id,name,email,profile_image',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المشروع بنجاح.',
            'data' => [
                'project' => $project,
            ],
        ], 201);
    }

    /**
     * عرض تفاصيل مشروع محدد.
     */
    public function show(
        Request $request,
        Team $team,
        Project $project
    ): JsonResponse {
        if (!$this->projectBelongsToTeam($project, $team)) {
            return response()->json([
                'success' => false,
                'message' => 'المشروع غير موجود ضمن هذا الفريق.',
            ], 404);
        }

        if (!$this->canAccessTeam($request, $team)) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية لمشاهدة هذا المشروع.',
            ], 403);
        }

        $project->load([
            'team:id,admin_id,team_name,join_code',
            'creator:id,name,email,profile_image',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'project' => $project,
            ],
        ]);
    }

    /**
     * تعديل المشروع.
     */
    public function update(
        Request $request,
        Team $team,
        Project $project
    ): JsonResponse {
        if (!$this->projectBelongsToTeam($project, $team)) {
            return response()->json([
                'success' => false,
                'message' => 'المشروع غير موجود ضمن هذا الفريق.',
            ], 404);
        }

        if ($team->admin_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن لمدير هذا الفريق فقط تعديل المشروع.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'status' => [
                'sometimes',
                Rule::in([
                    'pending',
                    'in_progress',
                    'completed',
                    'cancelled',
                ]),
            ],

            'start_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'end_date' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ]);

        $finalStartDate = array_key_exists('start_date', $validated)
            ? $validated['start_date']
            : $project->start_date;

        $finalEndDate = array_key_exists('end_date', $validated)
            ? $validated['end_date']
            : $project->end_date;

        if (
            $finalStartDate !== null &&
            $finalEndDate !== null &&
            Carbon::parse($finalEndDate)->lt(Carbon::parse($finalStartDate))
        ) {
            return response()->json([
                'message' => 'The end date must be after or equal to the start date.',
                'errors' => [
                    'end_date' => [
                        'تاريخ نهاية المشروع يجب أن يكون بعد تاريخ البداية أو مساويًا له.',
                    ],
                ],
            ], 422);
        }

        $project->update($validated);

        $project->load([
            'team:id,admin_id,team_name,join_code',
            'creator:id,name,email,profile_image',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل المشروع بنجاح.',
            'data' => [
                'project' => $project,
            ],
        ]);
    }

    /**
     * حذف المشروع.
     */
    public function destroy(
        Request $request,
        Team $team,
        Project $project
    ): JsonResponse {
        if (!$this->projectBelongsToTeam($project, $team)) {
            return response()->json([
                'success' => false,
                'message' => 'المشروع غير موجود ضمن هذا الفريق.',
            ], 404);
        }

        if ($team->admin_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'يمكن لمدير هذا الفريق فقط حذف المشروع.',
            ], 403);
        }

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المشروع بنجاح.',
        ]);
    }

    /**
     * التحقق أن المشروع موجود ضمن الفريق الموجود في الرابط.
     */
    private function projectBelongsToTeam(
        Project $project,
        Team $team
    ): bool {
        return $project->team_id === $team->id;
    }

    /**
     * مدير الفريق أو عضو فعال يستطيع مشاهدة بيانات الفريق.
     */
    private function canAccessTeam(
        Request $request,
        Team $team
    ): bool {
        if ($team->admin_id === $request->user()->id) {
            return true;
        }

        return $team->memberships()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->exists();
    }
}
