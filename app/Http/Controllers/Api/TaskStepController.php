<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskStepController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | عرض خطوات المهمة
    |--------------------------------------------------------------------------
    |
    | المدير يستطيع رؤية الخطوات.
    | المستخدم المسؤول عن المهمة يستطيع رؤيتها.
    | أي مستخدم آخر ممنوع.
    |
    */

    public function index(Request $request, Task $task)
    {
        // 1. المستخدم الحالي
        $user = $request->user();

        // 2. تحميل المشروع والفريق
        $task->load('project.team');

        $project = $task->project;
        $team = $project?->team;

        // 3. التأكد أن المشروع والفريق موجودان
        if (!$project || !$team) {
            return response()->json([
                'message' => 'The project or team related to this task was not found.'
            ], 404);
        }

        // 4. هل المستخدم مدير الفريق؟
        $isAdmin = $team->admin_id === $user->id;

        // 5. هل المستخدم المسؤول عن المهمة؟
        $isAssignedUser = $task->assigned_to === $user->id;

        // 6. السماح فقط للمدير أو المسؤول عن المهمة
        if (!$isAdmin && !$isAssignedUser) {
            return response()->json([
                'message' => 'You do not have permission to view steps of this task.'
            ], 403);
        }

        // 7. جلب الخطوات مرتبة
        $steps = $task->steps()
            ->orderBy('step_order')
            ->get();

        // 8. إرجاع الخطوات
        return response()->json([
            'message' => 'Task steps retrieved successfully.',
            'steps' => $steps
        ], 200);
    }


    /*
    |--------------------------------------------------------------------------
    | إضافة خطوة
    |--------------------------------------------------------------------------
    |
    | المدير فقط يستطيع إضافة خطوات.
    |
    */

    public function store(Request $request, Task $task)
    {
        // 1. المستخدم الحالي
        $user = $request->user();

        // 2. تحميل الفريق المرتبط بالمهمة
        $task->load('project.team');

        $project = $task->project;
        $team = $project?->team;

        // 3. التأكد من وجود المشروع والفريق
        if (!$project || !$team) {
            return response()->json([
                'message' => 'The project or team related to this task was not found.'
            ], 404);
        }

        // 4. التأكد أن المستخدم هو مدير الفريق
        if ($team->admin_id !== $user->id) {
            return response()->json([
                'message' => 'Only the team admin can add steps to this task.'
            ], 403);
        }

        // 5. التحقق من البيانات
        $validated = $request->validate([
            'step_description' => [
                'required',
                'string',
                'max:1000'
            ],

            'step_order' => [
                'nullable',
                'integer',
                'min:1'
            ],

            'points' => [
                'required',
                'integer',
                'min:0'
            ],
        ]);

        // 6. إنشاء الخطوة داخل transaction
        $step = DB::transaction(function () use ($task, $validated) {

            $order = $validated['step_order'] ?? null;

            // إذا لم يتم تحديد ترتيب
            if (is_null($order)) {

                $order = (int) $task->steps()
                    ->max('step_order') + 1;
            } else {

                // إزاحة الخطوات التالية
                $task->steps()
                    ->where('step_order', '>=', $order)
                    ->increment('step_order');
            }

            return $task->steps()->create([
                'step_description' => $validated['step_description'],
                'step_order' => $order,
                'points' => $validated['points'],
            ]);
        });

        // 7. إرجاع الخطوة
        return response()->json([
            'message' => 'Task step created successfully.',
            'step' => $step
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | تعديل الخطوة
    |--------------------------------------------------------------------------
    |
    | المدير فقط يستطيع تعديل وصف الخطوة.
    |
    */

    public function update(Request $request, TaskStep $step)
    {
        // 1. المستخدم الحالي
        $user = $request->user();

        // 2. تحميل المهمة والفريق
        $step->load('task.project.team');

        $task = $step->task;
        $team = $task?->project?->team;

        // 3. التأكد من وجود المهمة والفريق
        if (!$task || !$team) {
            return response()->json([
                'message' => 'The task or team related to this step was not found.'
            ], 404);
        }

        // 4. المدير فقط
        if ($team->admin_id !== $user->id) {
            return response()->json([
                'message' => 'Only the team admin can update this step.'
            ], 403);
        }

        // 5. التحقق من البيانات
        $validated = $request->validate([
            'step_description' => [
                'required',
                'string',
                'max:1000'
            ],

            'points' => [
                'required',
                'integer',
                'min:0'
            ],
        ]);

        // 6. تحديث وصف الخطوة
        $step->update([
            'step_description' => $validated['step_description'],
            'points' => $validated['points'],
        ]);

        // 7. إرجاع الخطوة
        return response()->json([
            'message' => 'Task step updated successfully.',
            'step' => $step
        ], 200);
    }


    /*
    |--------------------------------------------------------------------------
    | إعادة ترتيب الخطوات
    |--------------------------------------------------------------------------
    |
    | المدير فقط يستطيع إعادة ترتيب الخطوات.
    |
    */

    public function reorder(Request $request, Task $task)
    {
        // 1. المستخدم الحالي
        $user = $request->user();

        // 2. تحميل الفريق
        $task->load('project.team');

        $team = $task->project?->team;

        // 3. التأكد من وجود الفريق
        if (!$team) {
            return response()->json([
                'message' => 'The team related to this task was not found.'
            ], 404);
        }

        // 4. المدير فقط
        if ($team->admin_id !== $user->id) {
            return response()->json([
                'message' => 'Only the team admin can reorder steps.'
            ], 403);
        }

        // 5. التحقق من البيانات
        $validated = $request->validate([
            'steps' => [
                'required',
                'array',
                'min:1'
            ],

            'steps.*.step_id' => [
                'required',
                'integer',
                'distinct'
            ],

            'steps.*.step_order' => [
                'required',
                'integer',
                'min:1'
            ],
        ]);

        // 6. الخطوات المرسلة
        $incoming = collect($validated['steps']);

        // 7. خطوات المهمة
        $taskStepIds = $task->steps()->pluck('step_id');

        // 8. التأكد أن كل الخطوات تابعة لهذه المهمة
        $invalid = $incoming
            ->pluck('step_id')
            ->diff($taskStepIds);

        if ($invalid->isNotEmpty()) {
            return response()->json([
                'message' => 'Some steps do not belong to this task.'
            ], 422);
        }

        // 9. تحديث الترتيب
        DB::transaction(function () use ($incoming) {

            foreach ($incoming as $item) {

                TaskStep::where('step_id', $item['step_id'])
                    ->update([
                        'step_order' => $item['step_order']
                    ]);
            }
        });

        // 10. إعادة الخطوات مرتبة
        return response()->json([
            'message' => 'Task steps reordered successfully.',
            'steps' => $task->steps()
                ->orderBy('step_order')
                ->get()
        ], 200);
    }


    /*
    |--------------------------------------------------------------------------
    | حذف الخطوة
    |--------------------------------------------------------------------------
    |
    | المدير فقط يستطيع حذف الخطوة.
    |
    */

    public function destroy(Request $request, TaskStep $step)
    {
        // 1. المستخدم الحالي
        $user = $request->user();

        // 2. تحميل المهمة والفريق
        $step->load('task.project.team');

        $task = $step->task;
        $team = $task?->project?->team;

        // 3. التأكد من وجود المهمة والفريق
        if (!$task || !$team) {
            return response()->json([
                'message' => 'The task or team related to this step was not found.'
            ], 404);
        }

        // 4. المدير فقط
        if ($team->admin_id !== $user->id) {
            return response()->json([
                'message' => 'Only the team admin can delete this step.'
            ], 403);
        }

        // 5. حذف الخطوة وسد الفراغ
        DB::transaction(function () use ($task, $step) {

            $order = $step->step_order;

            $step->delete();

            $task->steps()
                ->where('step_order', '>', $order)
                ->decrement('step_order');
        });

        // 6. رسالة النجاح
        return response()->json([
            'message' => 'Task step deleted successfully.'
        ], 200);
    }
}
