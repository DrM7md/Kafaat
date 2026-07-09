<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\CandidateController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RevealController;
use App\Support\Permissions as P;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مسارات الـ API — الإصدار v1
|--------------------------------------------------------------------------
|
| البادئة `api/v1` مضبوطة في bootstrap/app.php.
| العقد الكامل موثّق في Brain/07-api-contract.md.
|
| ⚠️ `can:` حارسٌ خشن. الـ Policy تعرف السياق، والـ Resource يحرس الحقول.
|    الطبقات الثلاث معاً، لا واحدة منها. Brain/04-rbac.md §5.
|
*/

Route::get('/health', HealthController::class)->name('health');
Route::get('/settings/public', [SettingController::class, 'publicSettings'])->name('settings.public');
Route::get('/lookups', LookupController::class)->name('lookups.index');

// ── المصادقة ──
Route::post('/auth/register/employee', [RegisterController::class, 'employee'])
    ->middleware('throttle:6,1')->name('auth.register.employee');

Route::post('/auth/register/company', [RegisterController::class, 'company'])
    ->middleware('throttle:6,1')->name('auth.register.company');

Route::post('/auth/login', [SessionController::class, 'store'])
    ->middleware('throttle:5,1')->name('auth.login');

// ⚠️ بلا مصادقة، بتحقّق توقيع. راجع PaymentWebhookController.
Route::post('/payments/webhook/{provider}', PaymentWebhookController::class)
    ->name('payments.webhook');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [SessionController::class, 'destroy'])->name('auth.logout');
    Route::get('/me', MeController::class)->name('me');

    // ── حقوق صاحب البيانات (قانون رقم 13) ──
    Route::get('/me/data-export', [AccountController::class, 'export'])->name('me.export');
    Route::delete('/me', [AccountController::class, 'destroy'])->name('me.destroy');

    // ── الملفّ المهني (الموظّف) ──
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])
        ->middleware('can:'.P::PROFILE_UPDATE)->name('profile.update');
    Route::post('/profile/publish', [ProfileController::class, 'publish'])
        ->middleware('can:'.P::PROFILE_PUBLISH)->name('profile.publish');
    Route::post('/profile/unpublish', [ProfileController::class, 'unpublish'])
        ->middleware('can:'.P::PROFILE_PUBLISH)->name('profile.unpublish');
    Route::post('/profile/attachments', [ProfileController::class, 'uploadAttachment'])
        ->middleware('can:'.P::PROFILE_UPDATE)->name('profile.attachments.store');

    // ── المرفقات: لا رابط مباشر، Policy تحرس كل تنزيل ──
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])
        ->name('attachments.show');

    // ── التصفّح والكشف ★ ──
    Route::get('/candidates', [CandidateController::class, 'index'])
        ->middleware(['can:'.P::CANDIDATE_BROWSE, 'throttle:60,1'])->name('candidates.index');

    // لا `can:` هنا: المشرف يرى الملفّات دون أن يملك candidate.browse.
    // القرار للـ Policy لأنها تعرف السياق (من، وأي ملفّ).
    Route::get('/candidates/{profile}', [CandidateController::class, 'show'])
        ->middleware('throttle:100,1')->name('candidates.show');

    Route::post('/candidates/{profile}/reveal', RevealController::class)
        ->middleware(['can:'.P::CANDIDATE_REVEAL, 'throttle:10,1'])->name('candidates.reveal');

    // ── لوحة المشرف ──
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('can:'.P::RBAC_MANAGE)->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('can:'.P::RBAC_MANAGE)->name('roles.store');
        Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
            ->middleware('can:'.P::RBAC_MANAGE)->name('roles.permissions');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('can:'.P::RBAC_MANAGE)->name('roles.destroy');

        Route::get('/settings', [SettingController::class, 'index'])
            ->middleware('can:'.P::SETTINGS_VIEW)->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])
            ->middleware('can:'.P::SETTINGS_UPDATE)->name('settings.update');

        Route::get('/audit-logs', AuditLogController::class)
            ->middleware('can:'.P::AUDIT_VIEW)->name('audit.index');
    });
});
