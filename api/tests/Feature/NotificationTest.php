<?php

declare(strict_types=1);

use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Models\Payment;
use App\Models\User;
use App\Services\Notifications\Events;
use App\Services\Notifications\Notifier;
use App\Services\Reveal\RevealService;
use App\Services\Settings\SettingsRepository;
use App\Support\Permissions;
use App\Support\Roles;
use App\Support\SettingKeys;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * صندوق الإشعارات.
 *
 * ★★ الخطر الأوّل: تسريب اسم مرشّح داخل إشعار.
 *
 *    الإشعار صفٌّ **دائم**، والحجب حالةٌ **متغيّرة**: الاسترداد يُعيد الحجب.
 *    فاسمٌ خُزّن في إشعار «تمّ الكشف» يبقى مقروءاً في صندوق الشركة بعد
 *    استرداد مالها وفقدِ وصولها — تسريبٌ لا يُصلحه أي حارس لاحق.
 *
 * ★★ الخطر الثاني: المشرف العام بلا إشعار. صلاحياته من `Gate::before` لا من
 *    إسناد، فـ `User::permission()` تُسقطه — وهو ADR-018 يتكرّر.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(LookupSeeder::class);
    app(SettingsRepository::class)->flush();
});

function superAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Roles::SUPER_ADMIN);

    return $user;
}

/** يُنفّذ كشفاً كاملاً: دفع ثم webhook. */
function completeReveal(CompanyProfile $company, EmployeeProfile $profile): void
{
    app(RevealService::class)->reveal($company, $profile, 'key-'.uniqid());

    app(RevealService::class)->completeFromPayment(Payment::query()->latest('id')->firstOrFail());
}

it('★★ 1 — لا اسم مرشّحٍ في أي إشعار — ولو بعد كشفٍ مدفوع', function () {
    $profile = EmployeeProfile::factory()->create();
    $company = CompanyProfile::factory()->create();

    $name = $profile->user->name;

    completeReveal($company, $profile);

    $payload = DatabaseNotification::query()->get()
        ->map(fn (DatabaseNotification $n): string => json_encode($n->data, JSON_UNESCAPED_UNICODE) ?: '')
        ->implode(' ');

    // لو تسرّب الاسم هنا لبقي مقروءاً في صندوق الشركة **بعد** الاسترداد
    // الذي يُعيد الحجب — تسريبٌ دائم.
    expect($payload)->not->toContain($name);

    // و`ref` هو ما يُخزَّن بدلاً منه.
    expect($payload)->toContain($profile->ref);
});

it('2 — الموظّف يُعلَم بالشركة التي كشفته — هي من بادر فلا سرّ لها تجاهه', function () {
    $profile = EmployeeProfile::factory()->create();
    $company = CompanyProfile::factory()->create();

    completeReveal($company, $profile);

    $row = $this->actingAs($profile->user)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->json('data.0');

    expect($row['key'])->toBe('profile.revealed')
        ->and($row['body'])->toContain($company->legal_name)
        ->and($row['link'])->toBe('/profile');
});

it('★ 3 — الشركة تُعلَم باكتمال الكشف: بلا ذلك لا تعرف متى فُكّ الحجب', function () {
    $profile = EmployeeProfile::factory()->create();
    $company = CompanyProfile::factory()->create();

    completeReveal($company, $profile);

    $row = $this->actingAs($company->user)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->json('data.0');

    expect($row['key'])->toBe('reveal.completed')
        ->and($row['link'])->toBe("/candidates/{$profile->ref}");
});

it('4 — الاسترداد يُعلِم الشركة بأنّ الحجب عاد', function () {
    $profile = EmployeeProfile::factory()->create();
    $company = CompanyProfile::factory()->create();

    completeReveal($company, $profile);

    app(RevealService::class)->refund($company->reveals()->firstOrFail());

    $keys = array_column(
        $this->actingAs($company->user)->getJson('/api/v1/notifications')->json('data'),
        'key',
    );

    expect($keys)->toContain('reveal.refunded');
});

it('★★ 5 — المشرف العام يصله إشعار المشرفين رغم أنّ لا صلاحية مُسنَدة إليه', function () {
    $admin = superAdmin();

    expect($admin->getAllPermissions())->toHaveCount(0);   // الفخّ نفسه

    // إشعار «كفاءة تنتظر الاعتماد» لا يُرسَل إلّا وبوّابةُ الكفاءات مُشعَلة —
    // وهي مطفأة افتراضاً. نُشعلها لنختبر **وصول** الإشعار للمشرف العام.
    app(SettingsRepository::class)->set(SettingKeys::MODERATION_REQUIRE_CANDIDATE_APPROVAL, true);

    // ملفٌّ مكتملٌ بما يكفي لتجاوز عتبة النشر (60%) — بمعرّفاتٍ حقيقية من
    // البذرة لا بأرقامٍ ثابتة تنكسر إن تغيّر ترتيب البذر.
    $profile = EmployeeProfile::factory()->unpublished()->create([
        'title' => 'مهندس',
        'field_id' => Lookup::query()->group(Lookup::FIELD)->firstOrFail()->id,
        'city_id' => Lookup::query()->group(Lookup::CITY)->firstOrFail()->id,
        'employment_status_id' => Lookup::query()->group(Lookup::EMPLOYMENT_STATUS)->firstOrFail()->id,
        'years_experience' => 5,
        'summary' => 'نبذة كافية.',
    ]);

    $this->actingAs($profile->user)->postJson('/api/v1/profile/publish')->assertOk();

    $keys = array_column(
        $this->actingAs($admin)->getJson('/api/v1/notifications')->json('data'),
        'key',
    );

    expect($keys)->toContain('admin.profile_submitted');
});

it('6 — الاقتراح من «أخرى» يُبلَّغ به من يملك lookup.manage', function () {
    $manager = User::factory()->create();
    $manager->givePermissionTo(Permissions::LOOKUP_MANAGE);

    $profile = EmployeeProfile::factory()->create();

    $this->actingAs($profile->user)
        ->putJson('/api/v1/profile', ['field_other' => 'صناعة الطيران'])
        ->assertOk();

    $row = $this->actingAs($manager)->getJson('/api/v1/notifications')->json('data.0');

    expect($row['key'])->toBe('admin.lookup_suggested')
        ->and($row['body'])->toContain('صناعة الطيران')
        ->and($row['link'])->toBe('/admin/lookups');
});

it('7 — من لا يملك الصلاحية لا يصله إشعار المشرفين', function () {
    $outsider = EmployeeProfile::factory()->create()->user;

    $suggester = EmployeeProfile::factory()->create();

    $this->actingAs($suggester->user)
        ->putJson('/api/v1/profile', ['field_other' => 'قيمة جديدة'])
        ->assertOk();

    $keys = array_column(
        $this->actingAs($outsider)->getJson('/api/v1/notifications')->json('data'),
        'key',
    );

    expect($keys)->not->toContain('admin.lookup_suggested');
});

it('★ 8 — لا يرى أحدٌ صندوق غيره', function () {
    $profile = EmployeeProfile::factory()->create();
    $company = CompanyProfile::factory()->create();

    completeReveal($company, $profile);

    $employeeNotification = DatabaseNotification::query()
        ->where('notifiable_id', $profile->user_id)
        ->firstOrFail();

    // شركةٌ تحاول تعليم إشعار الموظّف: لا يُوجَد في نطاق استعلامها أصلاً.
    $this->actingAs($company->user)
        ->postJson("/api/v1/notifications/{$employeeNotification->id}/read")
        ->assertNotFound();

    expect($employeeNotification->fresh()->read_at)->toBeNull();
});

it('9 — تبويبتا «جديد» و«تمّ»: النقر ينقل الإشعار بينهما', function () {
    $profile = EmployeeProfile::factory()->create();
    completeReveal(CompanyProfile::factory()->create(), $profile);

    $id = $this->actingAs($profile->user)->getJson('/api/v1/notifications?filter=new')->json('data.0.id');

    expect($this->actingAs($profile->user)->getJson('/api/v1/notifications?filter=done')->json('data'))
        ->toBeEmpty();

    $this->actingAs($profile->user)->postJson("/api/v1/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    expect($this->actingAs($profile->user)->getJson('/api/v1/notifications?filter=new')->json('data'))
        ->toBeEmpty();

    expect($this->actingAs($profile->user)->getJson('/api/v1/notifications?filter=done')->json('data'))
        ->toHaveCount(1);
});

it('10 — «تعليم الكل» يُفرغ العدّاد', function () {
    $admin = superAdmin();

    // اقتراحان من موظّفَين.
    foreach (['قيمة أ', 'قيمة ب'] as $label) {
        $employee = EmployeeProfile::factory()->create();
        $this->actingAs($employee->user)->putJson('/api/v1/profile', ['field_other' => $label]);
    }

    expect($this->actingAs($admin)->getJson('/api/v1/notifications')->json('unread_count'))->toBe(2);

    $this->actingAs($admin)->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    expect($this->actingAs($admin)->getJson('/api/v1/notifications?filter=done')->json('data'))->toHaveCount(2);
});

it('11 — مفتاح features.notifications يُطفئها كلّها بنقرة', function () {
    app(SettingsRepository::class)->set(SettingKeys::FEATURE_NOTIFICATIONS, false);

    $profile = EmployeeProfile::factory()->create();
    completeReveal(CompanyProfile::factory()->create(), $profile);

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('12 — إشعار التسوية المالية يصل من يملك payment.view', function () {
    // نُنشئ اقتراحاً لنتأكّد أن الإشعارات تعمل، ثم نفحص كتالوج المفاتيح.
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permissions::PAYMENT_VIEW);

    $notification = Events::reconciliationRequired('pay_abc');

    app(Notifier::class)
        ->sendToPermission(Permissions::PAYMENT_VIEW, $notification);

    $row = $this->actingAs($viewer)->getJson('/api/v1/notifications')->json('data.0');

    expect($row['key'])->toBe('admin.reconciliation_required')
        ->and($row['body'])->toContain('pay_abc');
});

it('13 — الاعتماد والرفض يُبلَّغ بهما صاحب الملفّ', function () {
    $admin = superAdmin();

    $approved = EmployeeProfile::factory()->create();
    $rejected = EmployeeProfile::factory()->create();

    $this->actingAs($admin)->postJson("/api/v1/admin/candidates/{$approved->ref}/approve")->assertOk();
    $this->actingAs($admin)
        ->postJson("/api/v1/admin/candidates/{$rejected->ref}/reject", ['reason' => 'النبذة ناقصة جداً.'])
        ->assertOk();

    expect($this->actingAs($approved->user)->getJson('/api/v1/notifications')->json('data.0.key'))
        ->toBe('profile.approved');

    $row = $this->actingAs($rejected->user)->getJson('/api/v1/notifications')->json('data.0');

    expect($row['key'])->toBe('profile.rejected')
        ->and($row['body'])->toContain('النبذة ناقصة جداً.');
});

it('14 — تسجيل شركة يُبلَّغ به من يملك company.approve', function () {
    $admin = superAdmin();

    $this->postJson('/api/v1/auth/register/company', [
        'name' => 'مسؤول',
        'legal_name' => 'شركة الخليج للتقنية',
        'cr_number' => '1234567890',
        'email' => 'new-co@kafaat.qa',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'accept_terms' => true,
        'accept_privacy' => true,
    ])->assertCreated();

    $row = $this->actingAs($admin)->getJson('/api/v1/notifications')->json('data.0');

    expect($row['key'])->toBe('admin.company_registered')
        ->and($row['body'])->toContain('شركة الخليج للتقنية');
});

it('15 — القوائم المرجعية موجودة (حارس للبذرة التي تعتمد عليها الاختبارات)', function () {
    expect(Lookup::query()->approved()->count())->toBeGreaterThan(0);
});

/**
 * ★ صندوق الموظّف والشركة قائمةٌ **واحدة** لا تبويبتان.
 *
 * تبويبة «تمّ» للمشرف وحده: إشعاراته طابور عمل يحتاج متابعةً. أمّا إشعار
 * الموظّف فخبرٌ يُقرأ ويُنقر — ولو أخفيناه بعد قراءته لاختفى ما يريد الرجوع
 * إليه. لذا الافتراضي `all`: المقروء وغير المقروء معاً.
 */
it('★ 16 — الافتراضي يعرض المقروء وغير المقروء معاً', function () {
    $profile = EmployeeProfile::factory()->create();
    completeReveal(CompanyProfile::factory()->create(), $profile);

    $id = $this->actingAs($profile->user)->getJson('/api/v1/notifications')->json('data.0.id');

    $this->actingAs($profile->user)->postJson("/api/v1/notifications/{$id}/read")->assertOk();

    // مقروءٌ الآن — ومع ذلك يبقى في القائمة الافتراضية.
    $rows = $this->actingAs($profile->user)->getJson('/api/v1/notifications')->json('data');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['read_at'])->not->toBeNull();

    // بينما «جديد» تُخفيه — وهي تبويبة المشرف.
    expect($this->actingAs($profile->user)->getJson('/api/v1/notifications?filter=new')->json('data'))
        ->toBeEmpty();
});
