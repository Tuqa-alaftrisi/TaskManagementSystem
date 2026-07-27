<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamManagementController;
use App\Http\Controllers\Api\TeamMembershipController;
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
    Route::post('/register', [
        AuthController::class,
        'register',
    ]);

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
    | Authentication Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function () {
        Route::get('/me', [
            AuthController::class,
            'me',
        ]);

        Route::post('/logout', [
            AuthController::class,
            'logout',
        ]);

        Route::post('/logout-all', [
            AuthController::class,
            'logoutAll',
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Team Membership Routes
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
    | Team Viewing Routes
    |--------------------------------------------------------------------------
    |
    | متاحة للمدير أو العضو الفعال في الفريق.
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
    | Project Viewing Routes
    |--------------------------------------------------------------------------
    |
    | متاحة لمدير الفريق أو العضو الفعال فيه.
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
    | Admin Routes
    |--------------------------------------------------------------------------
    |
    | هذه المسارات متاحة فقط للحساب الذي دوره admin.
    | توجد أيضًا عمليات تحقق داخل Controllers للتأكد أن المدير يملك الفريق.
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

        // تعديل الفريق كاملًا
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

        // تعديل المشروع كاملًا
        Route::post('/teams/{team}/projects/{project}', [
            ProjectController::class,
            'update',
        ]);

        // حذف مشروع
        Route::delete('/teams/{team}/projects/{project}', [
            ProjectController::class,
            'destroy',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Team Management Routes (جديدة)
        |--------------------------------------------------------------------------
        |
        | إضافة عضو مباشرة، مراجعة طلبات الانضمام، البحث عن مستخدمين، إرسال دعوات.
        |
        */

        // إضافة عضو مباشرة إلى الفريق
        Route::post('/teams/{team}/members', [
            TeamManagementController::class,
            'addMember',
        ]);

        // مراجعة طلب انضمام (قبول أو رفض)
        Route::patch('/teams/{team}/join-requests/{joinRequest}', [
            TeamManagementController::class,
            'reviewJoinRequest',
        ]);

        // البحث عن مستخدمين لإرسال دعوة لهم
        Route::get('/teams/{team}/users/search', [
            TeamManagementController::class,
            'searchUsers',
        ]);

        // إرسال دعوة انضمام إلى مستخدم
        Route::post('/teams/{team}/invitations', [
            TeamManagementController::class,
            'sendInvitation',
        ]);
    });
});
