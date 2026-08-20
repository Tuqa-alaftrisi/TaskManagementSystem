<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StepCompletion;
use App\Models\TaskStep;
use App\Models\TeamMembership;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StepCompletionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Complete Step
    |--------------------------------------------------------------------------
    |
    | تسجيل إكمال المستخدم للخطوة.
    |
    */

    public function complete(Request $request, TaskStep $step)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | 1. تحميل المهمة والمشروع والفريق
        |--------------------------------------------------------------------------
        */

        $step->load('task.project.team');

        $task = $step->task;
        $project = $task?->project;
        $team = $project?->team;

        /*
        |--------------------------------------------------------------------------
        | 2. التأكد من وجود العلاقات
        |--------------------------------------------------------------------------
        */

        if (!$task || !$project || !$team) {
            return response()->json([
                'success' => false,
                'message' => 'The task, project, or team related to this step was not found.',
                'data' => null,
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. التأكد أن المستخدم هو المسؤول عن المهمة
        |--------------------------------------------------------------------------
        */

        if ($task->assigned_to !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the user assigned to this task can complete its steps.',
                'data' => null,
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. التأكد أن العضو Active في الفريق
        |--------------------------------------------------------------------------
        */

        $membership = TeamMembership::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'The user is not an active member of the project team.',
                'data' => null,
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. منع إكمال خطوة بعد إلغاء المهمة
        |--------------------------------------------------------------------------
        */

        if ($task->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot complete a step of a cancelled task.',
                'data' => null,
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 6. تنفيذ العملية داخل Transaction
        |--------------------------------------------------------------------------
        */

        $result = DB::transaction(function () use (
            $step,
            $task,
            $user,
            $membership
        ) {

            /*
            |--------------------------------------------------------------------------
            | البحث عن سجل الإكمال
            |--------------------------------------------------------------------------
            */

            $completion = StepCompletion::where('step_id', $step->step_id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            /*
            |--------------------------------------------------------------------------
            | منع أخذ النقاط أكثر من مرة
            |--------------------------------------------------------------------------
            */

            if ($completion && $completion->is_completed) {
                return [
                    'already_completed' => true,
                    'completion' => $completion,
                    'points_added' => 0,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | نقاط الخطوة
            |--------------------------------------------------------------------------
            */

            $points = (int) $step->points;

            /*
            |--------------------------------------------------------------------------
            | إنشاء أو تحديث سجل الإكمال
            |--------------------------------------------------------------------------
            */

            if (!$completion) {

                $completion = StepCompletion::create([
                    'step_id' => $step->step_id,
                    'user_id' => $user->id,
                    'is_completed' => true,
                    'completed_at' => now(),
                    'points_earned' => $points,
                ]);
            } else {

                $completion->update([
                    'is_completed' => true,
                    'completed_at' => now(),
                    'points_earned' => $points,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | إضافة نقاط الخطوة إلى رصيد العضو في الفريق
            |--------------------------------------------------------------------------
            */

            if ($points > 0) {
                $membership->increment('points_earned', $points);
            }

            /*
            |--------------------------------------------------------------------------
            | تحديث حالة المهمة تلقائيًا
            |--------------------------------------------------------------------------
            */

            $oldStatus = $task->status;

            $task->updateStatusFromProgress();

            /*
            |--------------------------------------------------------------------------
            | إعادة تحميل البيانات
            |--------------------------------------------------------------------------
            */

            $task->refresh();

            return [
                'already_completed' => false,
                'completion' => $completion->fresh(),
                'points_added' => $points,
                'old_status' => $oldStatus,
                'new_status' => $task->status,
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | 7. إذا كانت الخطوة مكتملة مسبقًا
        |--------------------------------------------------------------------------
        */

        if ($result['already_completed']) {
            return response()->json([
                'success' => true,
                'message' => 'This step has already been completed.',
                'data' => [
                    'completion' => $result['completion'],
                    'points_added' => 0,
                    'progress' => $task->getProgress(),
                    'status' => $task->status,
                ],
            ], 200);
        }

        if ($result['points_added'] > 0) {

            Notification::createNotification(
                $user->id,
                'You earned ' . $result['points_added'] . ' points for completing a task step.',
                Notification::TYPE_POINTS,
                'Points Earned',
                '/tasks/' . $task->task_id,
                [
                    'task_id' => $task->task_id,
                    'step_id' => $step->step_id,
                    'points_earned' => $result['points_added'],
                ]
            );
        }

        if ($result['old_status'] !== $result['new_status']) {

            Notification::createNotification(
                $user->id,
                'The status of your task has changed to ' . $result['new_status'] . '.',
                Notification::TYPE_TASK_STATUS_CHANGED,
                'Task Status Changed',
                '/tasks/' . $task->task_id,
                [
                    'task_id' => $task->task_id,
                    'old_status' => $result['old_status'],
                    'new_status' => $result['new_status'],
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 8. إرجاع النتيجة
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Step completed successfully.',
            'data' => [
                'completion' => $result['completion'],
                'points_added' => $result['points_added'],
                'total_team_points' => $membership->fresh()->points_earned,
                'progress' => $task->getProgress(),
                'status' => $task->status,
            ],
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Uncomplete Step
    |--------------------------------------------------------------------------
    |
    | إلغاء إكمال الخطوة وإرجاع نقاطها.
    |
    */

    public function uncomplete(Request $request, TaskStep $step)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | 1. تحميل العلاقات
        |--------------------------------------------------------------------------
        */

        $step->load('task.project.team');

        $task = $step->task;
        $project = $task?->project;
        $team = $project?->team;

        /*
        |--------------------------------------------------------------------------
        | 2. التأكد من وجود العلاقات
        |--------------------------------------------------------------------------
        */

        if (!$task || !$project || !$team) {
            return response()->json([
                'success' => false,
                'message' => 'The task, project, or team related to this step was not found.',
                'data' => null,
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. التأكد أن المستخدم مسؤول عن المهمة
        |--------------------------------------------------------------------------
        */

        if ($task->assigned_to !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the user assigned to this task can uncomplete its steps.',
                'data' => null,
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. التأكد أن العضو Active
        |--------------------------------------------------------------------------
        */

        $membership = TeamMembership::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'The user is not an active member of the project team.',
                'data' => null,
            ], 403);
        }

        if ($task->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change step completion of a cancelled task.',
                'data' => null,
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. تنفيذ العملية داخل Transaction
        |--------------------------------------------------------------------------
        */

        $result = DB::transaction(function () use (
            $step,
            $task,
            $user,
            $membership
        ) {

            /*
            |--------------------------------------------------------------------------
            | البحث عن سجل الإكمال
            |--------------------------------------------------------------------------
            */

            $completion = StepCompletion::where('step_id', $step->step_id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            /*
            |--------------------------------------------------------------------------
            | لا يوجد إكمال
            |--------------------------------------------------------------------------
            */

            if (!$completion || !$completion->is_completed) {
                return [
                    'was_completed' => false,
                    'points_removed' => 0,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | حفظ النقاط التي حصل عليها المستخدم
            |--------------------------------------------------------------------------
            */

            $points = (int) $completion->points_earned;

            /*
            |--------------------------------------------------------------------------
            | منع الرصيد من أن يصبح سالبًا
            |--------------------------------------------------------------------------
            */

            $pointsToRemove = min(
                $points,
                (int) $membership->points_earned
            );

            /*
            |--------------------------------------------------------------------------
            | طرح النقاط من رصيد العضو
            |--------------------------------------------------------------------------
            */

            if ($pointsToRemove > 0) {
                $membership->decrement(
                    'points_earned',
                    $pointsToRemove
                );
            }

            /*
            |--------------------------------------------------------------------------
            | إلغاء إكمال الخطوة
            |--------------------------------------------------------------------------
            */

            $completion->update([
                'is_completed' => false,
                'completed_at' => null,
                'points_earned' => 0,
            ]);

            /*
            |--------------------------------------------------------------------------
            | تحديث حالة المهمة تلقائيًا
            |--------------------------------------------------------------------------
            */

            $oldStatus = $task->status;

            $task->updateStatusFromProgress();

            /*
            |--------------------------------------------------------------------------
            | تحديث المهمة
            |--------------------------------------------------------------------------
            */

            $task->refresh();

            return [
                'was_completed' => true,
                'points_removed' => $pointsToRemove,
                'old_status' => $oldStatus,
                'new_status' => $task->status,
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | 6. إذا كانت الخطوة غير مكتملة أصلًا
        |--------------------------------------------------------------------------
        */

        if (!$result['was_completed']) {
            return response()->json([
                'success' => true,
                'message' => 'This step is not currently completed.',
                'data' => [
                    'points_removed' => 0,
                    'progress' => $task->getProgress(),
                    'status' => $task->status,
                ],
            ], 200);
        }

        if ($result['old_status'] !== $result['new_status']) {

            Notification::createNotification(
                $user->id,
                'The status of your task has changed to ' . $result['new_status'] . '.',
                Notification::TYPE_TASK_STATUS_CHANGED,
                'Task Status Changed',
                '/tasks/' . $task->task_id,
                [
                    'task_id' => $task->task_id,
                    'old_status' => $result['old_status'],
                    'new_status' => $result['new_status'],
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 7. إرجاع النتيجة
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Step completion cancelled successfully.',
            'data' => [
                'points_removed' => $result['points_removed'],
                'total_team_points' => $membership->fresh()->points_earned,
                'progress' => $task->getProgress(),
                'status' => $task->status,
            ],
        ], 200);
    }
}
