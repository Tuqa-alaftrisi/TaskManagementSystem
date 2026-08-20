<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\Notification;
use Illuminate\Http\Request;

class TaskController extends Controller


{
    public function index(Request $request, $team, $project)
    {
        // 1. المستخدم الحالي
        $user = $request->user();

        // 2. الحصول على الفريق والمشروع
        $team = Team::find($team);
        $project = Project::find($project);

        // 3. التأكد أن الفريق والمشروع موجودان
        if (!$team || !$project) {
            return response()->json([
                'message' => 'Team or project not found.'
            ], 404);
        }

        // 4. التأكد أن المشروع تابع للفريق
        if ($project->team_id !== $team->id) {
            return response()->json([
                'message' => 'The project does not belong to this team.'
            ], 403);
        }

        // 5. هل المستخدم مدير الفريق؟
        $isAdmin = $team->admin_id === $user->id;

        // 6. هل المستخدم عضو فعال في الفريق؟
        $isMember = TeamMembership::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        // 7. منع أي شخص ليس مديرًا أو عضوًا فعالًا
        if (!$isAdmin && !$isMember) {
            return response()->json([
                'message' => 'You do not have permission to view project tasks.'
            ], 403);
        }

        // 8. إنشاء استعلام مهام المشروع
        $query = Task::where('project_id', $project->id)
            ->with(['assignedUser', 'creator']);

        // 9. إذا كان المستخدم عضوًا وليس مديرًا
        //    يشاهد فقط المهام المسندة إليه
        if (!$isAdmin) {
            $query->where('assigned_to', $user->id);
        }

        // 10. تنفيذ الاستعلام
        $tasks = $query->latest()->get();

        // 11. إرجاع المهام
        return response()->json([
            'message' => 'Project tasks retrieved successfully.',
            'tasks' => $tasks
        ], 200);
    }

    public function store(Request $request, $team, $project)
    {
        // 1. التحقق من البيانات القادمة من الطلب
        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'deadline' => ['required', 'date', 'after:now'],
        ]);

        // 2. الحصول على المستخدم الحالي
        $user = $request->user();

        // 3. التأكد أن المستخدم الحالي مدير
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only admins can create tasks.'
            ], 403);
        }

        // 4. الحصول على الفريق والمشروع
        $team = Team::find($team);
        $project = Project::find($project);

        // 5. التأكد أن الفريق والمشروع موجودان
        if (!$team || !$project) {
            return response()->json([
                'message' => 'Team or project not found.'
            ], 404);
        }

        // 6. التأكد أن المشروع تابع لهذا الفريق
        if ($project->team_id !== $team->id) {
            return response()->json([
                'message' => 'The project does not belong to this team.'
            ], 403);
        }

        // 7. التأكد أن المدير الحالي هو مدير الفريق
        if ($team->admin_id !== $user->id) {
            return response()->json([
                'message' => 'You do not have permission to create a task in this project.'
            ], 403);
        }

        //6. التأكد أن المستخدم المكلف عضو فعال في فريق المشروع
        $membership = TeamMembership::where('team_id', $project->team_id)
            ->where('user_id', $validated['assigned_to'])
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json([
                'message' => 'The assigned user is not an active member of the project team.'
            ], 422);
        }

        // 7. إنشاء المهمة
        $task = Task::create([
            'project_id' => $project->id,
            'assigned_to' => $validated['assigned_to'],
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'deadline' => $validated['deadline'],
            'status' => Task::STATUS_PENDING,
        ]);

        Notification::createNotification(
            $task->assigned_to,
            'A new task has been assigned to you.',
            Notification::TYPE_TASK_ASSIGNED,
            'New Task Assigned',
            '/tasks/' . $task->task_id,
            [
                'task_id' => $task->task_id,
                'project_id' => $task->project_id,
                'assigned_by' => $user->id,
            ]
        );

        // 8. إرجاع المهمة التي تم إنشاؤها
        return response()->json([
            'message' => 'Task created successfully.',
            'task' => $task
        ], 201);
    }

    public function show(Request $request, $task)
    {
        // 1. المستخدم الحالي
        $user = $request->user();

        // 2. الحصول على المهمة
        $task = Task::with([
            'project.team',
            'assignedUser',
            'creator',
            'steps'
        ])->find($task);

        // 3. التأكد أن المهمة موجودة
        if (!$task) {
            return response()->json([
                'message' => 'Task not found.'
            ], 404);
        }

        // 4. الحصول على الفريق المرتبط بالمهمة
        $team = $task->project->team;
        // 5. التأكد من صلاحية المستخدم

        // هل المستخدم مدير الفريق؟
        $isAdmin = $team->admin_id === $user->id;

        // هل المستخدم هو المسؤول عن المهمة؟
        $isAssignedUser = $task->assigned_to === $user->id;

        // السماح فقط للمدير أو المستخدم المسؤول عن المهمة
        if (!$isAdmin && !$isAssignedUser) {
            return response()->json([
                'message' => 'You do not have permission to view this task.'
            ], 403);
        }

        // 7. إرجاع تفاصيل المهمة
        return response()->json([
            'message' => 'Task retrieved successfully.',
            'task' => $task
        ], 200);
    }
    public function update(Request $request, Task $task)
    {
        // 1. المستخدم الحالي
        $user = $request->user();

        // 2. تحميل المشروع والفريق المرتبطين بالمهمة
        $task->load('project.team');

        $project = $task->project;
        $team = $project?->team;

        // 3. التأكد أن المشروع والفريق موجودان
        if (!$project || !$team) {
            return response()->json([
                'message' => 'The project or team related to this task was not found.'
            ], 404);
        }

        // 4. التأكد أن المستخدم هو مدير الفريق
        if ($team->admin_id !== $user->id) {
            return response()->json([
                'message' => 'Only the team admin can update task details.'
            ], 403);
        }

        // 5. التحقق من بيانات التعديل
        $validated = $request->validate([

            'assigned_to' => [
                'sometimes',
                'integer',
                'exists:users,id'
            ],

            'title' => [
                'sometimes',
                'string',
                'max:255'
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string'
            ],

            'deadline' => [
                'sometimes',
                'date',
                'after:now'
            ],
        ]);

        // 6. إذا قام المدير بتغيير الشخص المسؤول
        if (isset($validated['assigned_to'])) {

            $membership = TeamMembership::where('team_id', $team->id)
                ->where('user_id', $validated['assigned_to'])
                ->where('status', 'active')
                ->exists();

            if (!$membership) {
                return response()->json([
                    'message' => 'The assigned user is not an active member of the project team.'
                ], 422);
            }
        }
        $oldAssignedTo = $task->assigned_to;
        // 7. تحديث تفاصيل المهمة
        $task->update($validated);

        if (
            isset($validated['assigned_to']) &&
            $oldAssignedTo != $validated['assigned_to']
        ) {
            Notification::createNotification(
                $validated['assigned_to'],
                'A task has been assigned to you.',
                Notification::TYPE_TASK_ASSIGNED,
                'Task Assigned',
                '/tasks/' . $task->task_id,
                [
                    'task_id' => $task->task_id,
                    'project_id' => $task->project_id,
                    'assigned_by' => $user->id,
                ]
            );
        }

        // 8. إعادة تحميل العلاقات
        $task->load([
            'project',
            'assignedUser',
            'creator',
            'steps'
        ]);

        // 9. إرجاع المهمة بعد التعديل
        return response()->json([
            'message' => 'Task updated successfully.',
            'task' => $task
        ], 200);
    }
    public function cancel(Request $request, Task $task)
    {
        // 1. المستخدم الحالي
        $user = $request->user();

        // 2. تحميل المشروع والفريق المرتبطين بالمهمة
        $task->load('project.team');

        $project = $task->project;
        $team = $project?->team;

        // 3. التأكد أن المشروع والفريق موجودان
        if (!$project || !$team) {
            return response()->json([
                'message' => 'The project or team related to this task was not found.'
            ], 404);
        }

        // 4. التأكد أن المستخدم هو مدير الفريق
        if ($team->admin_id !== $user->id) {
            return response()->json([
                'message' => 'Only the team admin can cancel this task.'
            ], 403);
        }

        // 5. منع إلغاء مهمة مكتملة
        if ($task->isCompleted()) {
            return response()->json([
                'message' => 'A completed task cannot be cancelled.'
            ], 422);
        }

        // 6. منع إلغاء مهمة ملغاة مسبقًا
        if ($task->isCancelled()) {
            return response()->json([
                'message' => 'The task is already cancelled.'
            ], 422);
        }

        // 7. تغيير الحالة إلى Cancelled
        $task->update([
            'status' => Task::STATUS_CANCELLED,
        ]);

        Notification::createNotification(
            $task->assigned_to,
            'The task assigned to you has been cancelled.',
            Notification::TYPE_TASK_CANCELLED,
            'Task Cancelled',
            '/tasks/' . $task->task_id,
            [
                'task_id' => $task->task_id,
                'cancelled_by' => $user->id,
            ]
        );

        // 8. إعادة تحميل العلاقات
        $task->load([
            'project',
            'assignedUser',
            'creator',
            'steps'
        ]);

        // 9. إرجاع النتيجة
        return response()->json([
            'message' => 'Task cancelled successfully.',
            'task' => $task
        ], 200);
    }

    public function destroy(Request $request, Task $task)
    {
        // 1. المستخدم الحالي
        $user = $request->user();

        // 2. تحميل المشروع والفريق المرتبطين بالمهمة
        $task->load('project.team');

        $project = $task->project;
        $team = $project?->team;

        // 3. التأكد أن المشروع والفريق موجودان
        if (!$project || !$team) {
            return response()->json([
                'message' => 'The project or team related to this task was not found.'
            ], 404);
        }

        // 4. التأكد أن المستخدم هو مدير هذا الفريق
        if ($team->admin_id !== $user->id) {
            return response()->json([
                'message' => 'You do not have permission to delete this task.'
            ], 403);
        }

        // 5. حذف المهمة
        $task->delete();

        // 6. إرجاع رسالة نجاح
        return response()->json([
            'message' => 'Task deleted successfully.'
        ], 200);
    }
    public function myTasks(Request $request)
    {
        // 1. المستخدم الحالي
        $user = $request->user();

        // 2. جلب المهام المسندة للمستخدم الحالي فقط
        $tasks = Task::where('assigned_to', $user->id)
            ->with([
                'project',
                'creator'
            ])
            ->latest()
            ->get();

        // 3. إرجاع المهام
        return response()->json([
            'message' => 'Your assigned tasks retrieved successfully.',
            'tasks' => $tasks
        ], 200);
    }
    public function checkDeadline(Task $task)
    {
        // 1. تحميل المشروع والفريق المرتبطين بالمهمة
        $task->load('project.team');

        $project = $task->project;
        $team = $project?->team;

        // 2. التأكد أن المشروع والفريق موجودان
        if (!$project || !$team) {
            return response()->json([
                'message' => 'The project or team related to this task was not found.'
            ], 404);
        }

        // 3. المستخدم الحالي
        $user = request()->user();

        // 4. هل المستخدم مدير الفريق؟
        $isAdmin = $team->admin_id === $user->id;

        // 5. هل المستخدم المسؤول عن المهمة؟
        $isAssignedUser = $task->assigned_to === $user->id;

        // 6. السماح فقط للمدير أو المسؤول عن المهمة
        if (!$isAdmin && !$isAssignedUser) {
            return response()->json([
                'message' => 'You do not have permission to view this task deadline.'
            ], 403);
        }

        // 7. التحقق من الموعد النهائي
        $isOverdue = $task->deadline->isPast()
            && !$task->isCompleted()
            && !$task->isCancelled();

        // 8. إرجاع النتيجة
        return response()->json([
            'message' => 'Task deadline checked successfully.',
            'task_id' => $task->task_id,
            'deadline' => $task->deadline,
            'is_overdue' => $isOverdue,
        ], 200);
    }
}
