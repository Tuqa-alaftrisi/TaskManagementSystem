<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamManagementController;
use App\Http\Controllers\Api\TeamMembershipController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskStepController;
use App\Http\Controllers\Api\StepCompletionController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
|
| هذه المسارات لا تحتاج إلى Token.
|
*/

Route::prefix('auth')->group(function () {

    // تسجيل مستخدم جديد
    Route::post('/register', [
        AuthController::class,
        'register',
    ]);

    // تسجيل الدخول
    Route::post('/login', [
        AuthController::class,
        'login',
    ]);
});


/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
|
| جميع المسارات الموجودة هنا تحتاج إلى Sanctum Token.
|
*/

Route::middleware('auth:sanctum')->group(function () {


    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function () {

        // معلومات المستخدم الحالي
        Route::get('/me', [
            AuthController::class,
            'me',
        ]);

        // تسجيل الخروج من الجهاز الحالي
        Route::post('/logout', [
            AuthController::class,
            'logout',
        ]);

        // تسجيل الخروج من جميع الأجهزة
        Route::post('/logout-all', [
            AuthController::class,
            'logoutAll',
        ]);
    });


    /*
    |--------------------------------------------------------------------------
    | My Tasks
    |--------------------------------------------------------------------------
    |
    | عرض المهام المسندة للمستخدم الحالي فقط.
    |
    */

    Route::get('/tasks/my', [
        TaskController::class,
        'myTasks',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Task Deadline
    |--------------------------------------------------------------------------
    |
    | فحص الموعد النهائي للمهمة.
    | يسمح فقط للمدير أو المستخدم المسؤول عن المهمة.
    |
    */

    Route::get('/tasks/{task}/deadline', [
        TaskController::class,
        'checkDeadline',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Team Membership
    |--------------------------------------------------------------------------
    |
    | متاحة للمستخدم العادي والمدير.
    |
    */

    // الانضمام إلى فريق باستخدام join_code
    Route::post('/teams/join', [
        TeamMembershipController::class,
        'join',
    ]);

    // مغادرة الفريق
    Route::post('/teams/{team}/leave', [
        TeamMembershipController::class,
        'leave',
    ]);

    // عرض أعضاء الفريق
    Route::get('/teams/{team}/members', [
        TeamMembershipController::class,
        'index',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Team Viewing
    |--------------------------------------------------------------------------
    |
    | عرض الفرق التي ينتمي إليها المستخدم.
    |
    */

    // عرض فرق المستخدم الحالي
    Route::get('/teams', [
        TeamController::class,
        'index',
    ]);

    // عرض فريق محدد
    Route::get('/teams/{team}', [
        TeamController::class,
        'show',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Project Viewing
    |--------------------------------------------------------------------------
    |
    | عرض المشاريع ضمن الفريق.
    |
    */

    // عرض مشاريع فريق محدد
    Route::get('/teams/{team}/projects', [
        ProjectController::class,
        'index',
    ]);

    // عرض مشروع محدد ضمن فريق
    Route::get('/teams/{team}/projects/{project}', [
        ProjectController::class,
        'show',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Task Viewing
    |--------------------------------------------------------------------------
    |
    | عرض المهام.
    |
    */

    // عرض مهام مشروع
    Route::get('/teams/{team}/projects/{project}/tasks', [
        TaskController::class,
        'index',
    ]);

    // عرض تفاصيل مهمة
    Route::get('/tasks/{task}', [
        TaskController::class,
        'show',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Task Steps - Viewing
    |--------------------------------------------------------------------------
    |
    | المدير أو المستخدم المسؤول عن المهمة يستطيع مشاهدة خطواتها.
    |
    */

    // عرض خطوات المهمة
    Route::get('/tasks/{task}/steps', [
        TaskStepController::class,
        'index',
    ]);

    /*
|--------------------------------------------------------------------------
| Step Completion
|--------------------------------------------------------------------------
|
| المستخدم المسؤول عن المهمة يستطيع:
| - تسجيل إكمال الخطوة
| - إلغاء إكمال الخطوة
|
| حالة المهمة والنقاط يتم تحديثهما تلقائيًا.
|
*/

    // إكمال خطوة
    Route::post('/steps/{step}/complete', [
        StepCompletionController::class,
        'complete',
    ]);

    // إلغاء إكمال خطوة
    Route::delete('/steps/{step}/complete', [
        StepCompletionController::class,
        'uncomplete',
    ]);
    /*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
|
| جميع عمليات الإشعارات تخص المستخدم الحالي فقط.
|
*/

    Route::prefix('notifications')->group(function () {

        // عرض جميع إشعارات المستخدم الحالي
        Route::get('/', [
            NotificationController::class,
            'index',
        ]);

        // عدد الإشعارات غير المقروءة
        Route::get('/unread-count', [
            NotificationController::class,
            'unreadCount',
        ]);

        // تحديد جميع الإشعارات كمقروءة
        Route::patch('/read-all', [
            NotificationController::class,
            'markAllAsRead',
        ]);

        // عرض إشعار محدد
        Route::get('/{notification}', [
            NotificationController::class,
            'show',
        ]);

        // تحديد إشعار محدد كمقروء
        Route::patch('/{notification}/read', [
            NotificationController::class,
            'markAsRead',
        ]);

        // حذف إشعار
        Route::delete('/{notification}', [
            NotificationController::class,
            'destroy',
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    |
    | جميع المسارات الموجودة هنا متاحة فقط للحساب الذي دوره admin.
    |
    */

    Route::middleware('role:admin')->group(function () {


        /*
        |--------------------------------------------------------------------------
        | Team Management
        |--------------------------------------------------------------------------
        */

        // إنشاء فريق
        Route::post('/teams', [
            TeamController::class,
            'store',
        ]);

        // تعديل الفريق
        Route::post('/teams/{team}', [
            TeamController::class,
            'update',
        ]);

        // حذف الفريق
        Route::delete('/teams/{team}', [
            TeamController::class,
            'destroy',
        ]);

        // حذف عضو من الفريق
        Route::delete('/teams/{team}/members/{user}', [
            TeamMembershipController::class,
            'remove',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Project Management
        |--------------------------------------------------------------------------
        */

        // إنشاء مشروع ضمن فريق
        Route::post('/teams/{team}/projects', [
            ProjectController::class,
            'store',
        ]);

        // تعديل المشروع
        Route::post('/teams/{team}/projects/{project}', [
            ProjectController::class,
            'update',
        ]);

        // حذف المشروع
        Route::delete('/teams/{team}/projects/{project}', [
            ProjectController::class,
            'destroy',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Team Management
        |--------------------------------------------------------------------------
        |
        | إضافة أعضاء، مراجعة الطلبات، البحث، وإرسال الدعوات.
        |
        */

        // إضافة عضو مباشرة
        Route::post('/teams/{team}/members', [
            TeamManagementController::class,
            'addMember',
        ]);

        // مراجعة طلب انضمام
        Route::patch('/teams/{team}/join-requests/{joinRequest}', [
            TeamManagementController::class,
            'reviewJoinRequest',
        ]);

        // البحث عن مستخدمين
        Route::get('/teams/{team}/users/search', [
            TeamManagementController::class,
            'searchUsers',
        ]);

        // إرسال دعوة
        Route::post('/teams/{team}/invitations', [
            TeamManagementController::class,
            'sendInvitation',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Task Management
        |--------------------------------------------------------------------------
        |
        | إنشاء وتعديل وحذف المهام للمدير فقط.
        |
        */

        // إنشاء مهمة ضمن مشروع
        Route::post('/teams/{team}/projects/{project}/tasks', [
            TaskController::class,
            'store',
        ]);

        // تعديل تفاصيل المهمة
        Route::put('/tasks/{task}', [
            TaskController::class,
            'update',
        ]);

        // إلغاء مهمة
        Route::patch('/tasks/{task}/cancel', [
            TaskController::class,
            'cancel',
        ]);

        // حذف المهمة
        Route::delete('/tasks/{task}', [
            TaskController::class,
            'destroy',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Task Steps Management
        |--------------------------------------------------------------------------
        |
        | إدارة خطوات المهمة للمدير فقط.
        |
        */

        // إضافة خطوة للمهمة
        Route::post('/tasks/{task}/steps', [
            TaskStepController::class,
            'store',
        ]);

        // تعديل وصف خطوة
        Route::put('/steps/{step}', [
            TaskStepController::class,
            'update',
        ]);

        // إعادة ترتيب خطوات المهمة
        Route::put('/tasks/{task}/steps/reorder', [
            TaskStepController::class,
            'reorder',
        ]);

        // حذف خطوة
        Route::delete('/steps/{step}', [
            TaskStepController::class,
            'destroy',
        ]);
    });
});
