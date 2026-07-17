<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\CandidateApprovalController;
use App\Http\Controllers\Api\V1\Admin\CompanyController;
use App\Http\Controllers\Api\V1\Admin\LookupController as AdminLookupController;
use App\Http\Controllers\Api\V1\Admin\RevealPricingController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\CandidateController;
use App\Http\Controllers\Api\V1\EmployerController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\JobBoardController;
use App\Http\Controllers\Api\V1\JobInterestController;
use App\Http\Controllers\Api\V1\JobPostingController;
use App\Http\Controllers\Api\V1\JobSlotController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RevealController;
use App\Http\Controllers\Api\V1\RevealListController;
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

// بياناتٌ مرجعية شبه ثابتة (المجالات، المدن، حالات التوظيف). ETag يجعل
// إعادة التحميل تردّ 304 بلا جسم، و`max_age` يجعل المتصفّح لا يسأل أصلاً.
//
// ⚠️ لا تُضِف `settings/public` هنا: تعديل المشرف يجب أن يظهر فوراً.
// ⚠️ ولا تُخزَّن القوائم خمس دقائق كما كانت: يوم كانت بذرةً ثابتة لم يضرّ
// ذلك. أمّا الآن وقد صارت تُدار من اللوحة (وتقبل اقتراحات «أخرى»)، فتخزينها
// يعني أن يعتمد المشرف مدينةً ولا يراها الناس خمس دقائق — وهو عين ما يحذّر
// منه السطر أعلاه. نُبقي الـ ETag: التحقّق رخيص (304 بلا جسم)، والتحديث فوري.
Route::get('/lookups', LookupController::class)
    ->middleware('cache.headers:private;max_age=0;must_revalidate;etag')
    ->name('lookups.index');

// ── المصادقة ──
Route::post('/auth/register/employee', [RegisterController::class, 'employee'])
    ->middleware('throttle:6,1')->name('auth.register.employee');

Route::post('/auth/register/company', [RegisterController::class, 'company'])
    ->middleware('throttle:6,1')->name('auth.register.company');

Route::post('/auth/login', [SessionController::class, 'store'])
    ->middleware('throttle:5,1')->name('auth.login');

// ⚠️ بلا مصادقة، بتحقّق توقيع. راجع PaymentWebhookController.
// throttle حدٌّ أعلى معقول لإعادات البوّابة يمنع الإغراق (لا يمسّ الأمان —
// التوقيع يحرسه — بل يمنع استنزاف الموارد بطلباتٍ مزوّرة بلا حساب).
Route::post('/payments/webhook/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:60,1')->name('payments.webhook');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [SessionController::class, 'destroy'])->name('auth.logout');
    Route::get('/me', MeController::class)->name('me');

    // ── الإشعارات ──
    // بلا حارس صلاحية عمداً: كل استعلامٍ مقيّدٌ بعلاقة المستخدم نفسه، فلا
    // يمكن الوصول إلى صندوق غيره أصلاً.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read_all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');

    // ── الحساب: ما يملكه المستخدم عن نفسه ──
    // بلا حارس صلاحية عمداً: يعدّل نفسه لا غيره، والاستعلام لا يخرج من صفّه.
    // ما يملكه الخادم (النوع، الحالة، التوثيق) محجوبٌ في `UpdateAccountRequest`.
    //
    // ★ throttle هنا يقطع أوراكل تعداد الحسابات: تحقّق `email.unique` كان
    //   يكشف تسجيلَ أي بريدٍ لمهاجمٍ مصادَق بلا حدّ (يُبدّل بريده لبريد الهدف
    //   ويقرأ «مسجَّل لحسابٍ آخر»). عشرون في الدقيقة تكفي التعديلَ الشرعيّ.
    Route::put('/me', [AccountController::class, 'update'])
        ->middleware('throttle:20,1')->name('me.update');
    Route::put('/me/password', [AccountController::class, 'updatePassword'])
        ->middleware('throttle:6,1')->name('me.password');

    // ── حقوق صاحب البيانات (قانون رقم 13) ──
    // تصدير البيانات استعلامٌ ثقيل (كل ملفّ المستخدم وعلاقاته) — throttle
    // يمنع إساءةً تستنزف القاعدة.
    Route::get('/me/data-export', [AccountController::class, 'export'])
        ->middleware('throttle:6,1')->name('me.export');
    Route::delete('/me', [AccountController::class, 'destroy'])->name('me.destroy');

    // ── الملفّ المهني (الموظّف) ──
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])
        ->middleware('can:'.P::PROFILE_UPDATE)->name('profile.update');

    // الشركات المسجَّلة — ليختار الموظّف جهةَ عمله فتُحجَب عنه. راجع
    // EmployerController: هي الوصلة التي كان عمى صاحب العمل معطَّلاً بدونها.
    Route::get('/employers', EmployerController::class)
        ->middleware('can:'.P::PROFILE_UPDATE)->name('employers.index');

    // ★ تجديد نضارة الحالة بنقرة — أرخص فعلٍ في المنصّة وأثمن ما فيها.
    Route::post('/profile/readiness/confirm', [ProfileController::class, 'confirmReadiness'])
        ->middleware('can:'.P::PROFILE_UPDATE)->name('profile.readiness.confirm');
    Route::post('/profile/publish', [ProfileController::class, 'publish'])
        ->middleware('can:'.P::PROFILE_PUBLISH)->name('profile.publish');
    Route::post('/profile/unpublish', [ProfileController::class, 'unpublish'])
        ->middleware('can:'.P::PROFILE_PUBLISH)->name('profile.unpublish');
    Route::delete('/profile/attachments/{attachment}', [ProfileController::class, 'deleteAttachment'])
        ->middleware('can:'.P::PROFILE_UPDATE)->name('profile.attachments.destroy');

    Route::post('/profile/attachments', [ProfileController::class, 'uploadAttachment'])
        ->middleware('can:'.P::PROFILE_UPDATE)->name('profile.attachments.store');

    // ── المرفقات: لا رابط مباشر، Policy تحرس كل تنزيل ──
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])
        ->name('attachments.show');

    // ── التصفّح والكشف ★ ──
    // ★★ `company.verified` على كل مسارٍ يُري ملفّات الناس: شركةٌ غير موثَّقة
    //    لا تتصفّح ولا تكشف. الحارس يمرّ عن المشرف والموظّف بلا مساس — راجع
    //    EnsureCompanyVerified.
    Route::get('/candidates', [CandidateController::class, 'index'])
        ->middleware(['can:'.P::CANDIDATE_BROWSE, 'company.verified', 'throttle:60,1'])
        ->name('candidates.index');

    // لا `can:` هنا: المشرف يرى الملفّات دون أن يملك candidate.browse.
    // القرار للـ Policy لأنها تعرف السياق (من، وأي ملفّ).
    Route::get('/candidates/{profile}', [CandidateController::class, 'show'])
        ->middleware(['company.verified', 'throttle:100,1'])->name('candidates.show');

    Route::post('/candidates/{profile}/reveal', RevealController::class)
        ->middleware(['can:'.P::CANDIDATE_REVEAL, 'company.verified', 'throttle:10,1'])
        ->name('candidates.reveal');

    // `/reveals` بلا حارس: سجلُّ كشوفٍ سابقة لا يُري ملفّاً جديداً. وشركةٌ
    // أُلغي توثيقها لا يجوز أن تُحرَم من رؤية ما دفعت فيه.
    Route::get('/reveals', RevealListController::class)
        ->middleware('can:'.P::CANDIDATE_REVEAL)->name('reveals.index');

    // ── الوظائف: جانب الشركة ──
    // `company.verified` هنا أيضاً: شركةٌ لم يتحقّق منها إنسان لا تنشر وظيفةً
    // تستدرج بها الناس، ولا ترى بطاقاتِ من أُعجب بها.
    Route::get('/my-jobs', [JobPostingController::class, 'index'])
        ->middleware(['can:'.P::JOB_MANAGE, 'company.verified'])->name('my-jobs.index');
    Route::post('/my-jobs', [JobPostingController::class, 'store'])
        ->middleware(['can:'.P::JOB_MANAGE, 'company.verified'])->name('my-jobs.store');
    Route::put('/my-jobs/{job}', [JobPostingController::class, 'update'])
        ->middleware(['can:'.P::JOB_MANAGE, 'company.verified'])->name('my-jobs.update');
    Route::post('/my-jobs/{job}/open', [JobPostingController::class, 'open'])
        ->middleware(['can:'.P::JOB_MANAGE, 'company.verified'])->name('my-jobs.open');
    Route::post('/my-jobs/{job}/close', [JobPostingController::class, 'close'])
        ->middleware(['can:'.P::JOB_MANAGE, 'company.verified'])->name('my-jobs.close');
    Route::delete('/my-jobs/{job}', [JobPostingController::class, 'destroy'])
        ->middleware(['can:'.P::JOB_MANAGE, 'company.verified'])->name('my-jobs.destroy');

    // ★★ المعجبون — بطاقاتٌ **محجوبة** وأزرارُ كشف. نفس الـ Resource الذي
    //    يخدم التصفّح، فيسري عليه الحجب والدفع تلقائياً.
    Route::get('/my-jobs/{job}/interested', JobInterestController::class)
        ->middleware(['can:'.P::JOB_MANAGE, 'company.verified'])->name('my-jobs.interested');

    // شراء خانة إضافية — دفعةٌ بغرض `job_slot`، تُتمّها webhook كالكشف.
    Route::post('/job-slots/purchase', JobSlotController::class)
        ->middleware(['can:'.P::JOB_MANAGE, 'company.verified', 'throttle:10,1'])
        ->name('job-slots.purchase');

    // ── الوظائف: جانب الموظّف ──
    Route::get('/jobs', [JobBoardController::class, 'index'])
        ->middleware(['can:'.P::JOB_BROWSE, 'throttle:60,1'])->name('jobs.index');
    Route::get('/jobs/{job}', [JobBoardController::class, 'show'])
        ->middleware('can:'.P::JOB_BROWSE)->name('jobs.show');
    Route::post('/jobs/{job}/interest', [JobBoardController::class, 'interest'])
        ->middleware(['can:'.P::JOB_INTEREST, 'throttle:30,1'])->name('jobs.interest');
    Route::delete('/jobs/{job}/interest', [JobBoardController::class, 'withdraw'])
        ->middleware('can:'.P::JOB_INTEREST)->name('jobs.interest.withdraw');

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

        // ── القوائم المرجعية ──
        // «أضِف مجالاً» و«افتح الإمارات بدرهمها» و«اعتمِد ما اقترحه موظّف»
        // — كلّها هنا، بلا سطر كود. Brain/05 §4.
        Route::get('/lookups', [AdminLookupController::class, 'index'])
            ->middleware('can:'.P::LOOKUP_MANAGE)->name('lookups.index');
        Route::post('/lookups', [AdminLookupController::class, 'store'])
            ->middleware('can:'.P::LOOKUP_MANAGE)->name('lookups.store');
        Route::put('/lookups/{lookup}', [AdminLookupController::class, 'update'])
            ->middleware('can:'.P::LOOKUP_MANAGE)->name('lookups.update');
        Route::post('/lookups/{lookup}/approve', [AdminLookupController::class, 'approve'])
            ->middleware('can:'.P::LOOKUP_MANAGE)->name('lookups.approve');
        Route::post('/lookups/{lookup}/reject', [AdminLookupController::class, 'reject'])
            ->middleware('can:'.P::LOOKUP_MANAGE)->name('lookups.reject');
        Route::delete('/lookups/{lookup}', [AdminLookupController::class, 'destroy'])
            ->middleware('can:'.P::LOOKUP_MANAGE)->name('lookups.destroy');

        // ── تسعير الكشف لكل مجال ──
        // فعلٌ ماليّ، فيُحرَس بـ settings.update لا بـ lookup.manage: من يُهذّب
        // تسمية مدينة ليس بالضرورة من يُسعّر.
        Route::get('/reveal-pricing', [RevealPricingController::class, 'index'])
            ->middleware('can:'.P::SETTINGS_VIEW)->name('reveal-pricing.index');
        Route::put('/reveal-pricing/{field}', [RevealPricingController::class, 'update'])
            ->middleware('can:'.P::SETTINGS_UPDATE)->name('reveal-pricing.update');

        // ── الشركات ──
        Route::get('/companies', [CompanyController::class, 'index'])
            ->middleware('can:'.P::COMPANY_VIEW)->name('companies.index');
        Route::get('/companies/{company}', [CompanyController::class, 'show'])
            ->middleware('can:'.P::COMPANY_VIEW)->name('companies.show');
        Route::put('/companies/{company}/verification', [CompanyController::class, 'verify'])
            ->middleware('can:'.P::COMPANY_APPROVE)->name('companies.verify');
        Route::delete('/companies/bulk', [CompanyController::class, 'bulkDestroy'])
            ->middleware('can:'.P::USER_MANAGE)->name('companies.bulk');
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])
            ->middleware('can:'.P::USER_MANAGE)->name('companies.destroy');

        // ── طلبات اعتماد الكفاءات (القائمة محجوبة عمداً) ──
        Route::get('/candidates', [CandidateApprovalController::class, 'index'])
            ->middleware('can:'.P::PROFILE_APPROVE)->name('candidates.index');
        Route::post('/candidates/{profile}/approve', [CandidateApprovalController::class, 'approve'])
            ->middleware('can:'.P::PROFILE_APPROVE)->name('candidates.approve');
        Route::post('/candidates/{profile}/reject', [CandidateApprovalController::class, 'reject'])
            ->middleware('can:'.P::PROFILE_APPROVE)->name('candidates.reject');
        Route::delete('/candidates/bulk', [CandidateApprovalController::class, 'bulkDestroy'])
            ->middleware('can:'.P::USER_MANAGE)->name('candidates.bulk');
        Route::delete('/candidates/{profile}', [CandidateApprovalController::class, 'destroy'])
            ->middleware('can:'.P::USER_MANAGE)->name('candidates.destroy');
    });
});
