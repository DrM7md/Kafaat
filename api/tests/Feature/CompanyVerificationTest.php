<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use App\Support\Roles;
use App\Support\SettingKeys;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * ★★ بوّابة الشركات: لا تصفّح ولا كشف قبل توثيق المشرف.
 *
 * الخطر الذي تحرسه هذه الاختبارات: **من يتصفّح هو من يرى ملفّات الناس**.
 * شركةٌ وهمية تتصفّح تعني حصادَ بياناتٍ بلا حسيب — والحقلُ `verification_status`
 * كان موجوداً قبل هذا العمل، والمشرف يضبطه، **ولا شيء يفرضه**.
 *
 * والبوّابة المقابلة (اعتماد الكفاءات) **مطفأة** عمداً: الاحتكاك هناك يطرد
 * المرشّحين وهم الطرف الشحيح، وهم ليسوا من يرى أحداً.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(LookupSeeder::class);
    app(SettingsRepository::class)->flush();
});

function verifier(): User
{
    $user = User::factory()->create();
    $user->assignRole(Roles::SUPER_ADMIN);

    return $user;
}

it('★★ 1 — شركةٌ قيد المراجعة لا تتصفّح الكفاءات', function () {
    EmployeeProfile::factory()->create();

    $company = CompanyProfile::factory()->pending()->create();

    $this->actingAs($company->user)
        ->getJson('/api/v1/candidates')
        ->assertForbidden()
        ->assertJsonPath('verification_status', 'pending');
});

it('★★ 2 — ولا تفتح ملفّاً بعينه، ولا تكشف — ولو بمعرّفه المباشر', function () {
    $profile = EmployeeProfile::factory()->create();
    $company = CompanyProfile::factory()->pending()->create();

    // إخفاءُ زرٍّ في الواجهة لا يمنع طلباً بـ curl. الضمان في الخادم.
    $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertForbidden();

    $this->actingAs($company->user)
        ->withHeader('Idempotency-Key', 'k-'.uniqid())
        ->postJson("/api/v1/candidates/{$profile->ref}/reveal")
        ->assertForbidden();
});

it('3 — الشركة المرفوضة تُمنَع كذلك، برسالةٍ تخصّها', function () {
    EmployeeProfile::factory()->create();

    $company = CompanyProfile::factory()->rejected()->create();

    $this->actingAs($company->user)
        ->getJson('/api/v1/candidates')
        ->assertForbidden()
        ->assertJsonPath('verification_status', 'rejected');
});

it('★ 4 — التوثيق يفتح الباب فوراً، ويُبلَّغ به صاحبه', function () {
    EmployeeProfile::factory()->create();

    $company = CompanyProfile::factory()->pending()->create();

    $this->actingAs($company->user)->getJson('/api/v1/candidates')->assertForbidden();

    $this->actingAs(verifier())
        ->putJson("/api/v1/admin/companies/{$company->id}/verification", ['status' => 'verified'])
        ->assertOk();

    // `fresh()` لأن نسخة المستخدم في ذاكرة الاختبار تحمل علاقةً حُمّلت قبل
    // التوثيق. في الإنتاج لا وجود لهذا: كل طلبٍ يُحمّل المستخدم من القاعدة.
    $verified = $company->user->fresh();

    // البابُ مفتوح — بلا خروجٍ ولا دخول.
    $this->actingAs($verified)->getJson('/api/v1/candidates')->assertOk();

    $keys = array_column(
        $this->actingAs($verified)->getJson('/api/v1/notifications')->json('data'),
        'key',
    );

    expect($keys)->toContain('company.verified');
});

it('5 — الرفض يُبلَّغ به صاحبه أيضاً', function () {
    $company = CompanyProfile::factory()->pending()->create();

    $this->actingAs(verifier())
        ->putJson("/api/v1/admin/companies/{$company->id}/verification", ['status' => 'rejected'])
        ->assertOk();

    $keys = array_column(
        $this->actingAs($company->user)->getJson('/api/v1/notifications')->json('data'),
        'key',
    );

    expect($keys)->toContain('company.rejected');
});

it('6 — إعادة الحفظ على الحالة نفسها لا تُكرّر الإشعار', function () {
    $company = CompanyProfile::factory()->pending()->create();

    for ($click = 0; $click < 3; $click++) {
        $this->actingAs(verifier())
            ->putJson("/api/v1/admin/companies/{$company->id}/verification", ['status' => 'verified'])
            ->assertOk();
    }

    // ثلاث نقرات من المشرف ⇒ إشعارٌ واحد. وإلّا أغرقنا الشركة.
    expect(DatabaseNotification::query()
        ->where('notifiable_id', $company->user_id)
        ->count())->toBe(1);
});

it('★★ 7 — الحارس لا يمسّ المشرف: يتصفّح بلا ملفّ شركةٍ أصلاً', function () {
    EmployeeProfile::factory()->create();

    // سؤال «هل هذه الشركة موثَّقة؟» لا معنى له لمن ليس شركة. وردُّ المشرف
    // هنا كان سيحجبه عن تصفّحٍ يملكه بحقّ.
    $this->actingAs(verifier())->getJson('/api/v1/candidates')->assertOk();
});

it('8 — التسجيل يضع الشركة «قيد المراجعة» لا «غير موثَّقة»', function () {
    $this->postJson('/api/v1/auth/register/company', [
        'name' => 'مسؤول',
        'legal_name' => 'شركة تجريبية',
        'cr_number' => '1234567890',
        'email' => 'brand-new@kafaat.qa',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'accept_terms' => true,
        'accept_privacy' => true,
    ])->assertCreated();

    $company = User::query()->where('email', 'brand-new@kafaat.qa')->firstOrFail()->companyProfile;

    // «غير موثَّقة» حالةُ إهمال؛ و«قيد المراجعة» وعدٌ بأنّ طلباً ينتظر قراراً.
    expect($company->verification_status)->toBe(VerificationStatus::Pending);

    // ولا تتصفّح شيئاً بعد.
    $this->actingAs($company->user)->getJson('/api/v1/candidates')->assertForbidden();
});

it('9 — سجلّ الكشوف السابقة يبقى مفتوحاً: ما دُفع فيه لا يُحجب', function () {
    // شركةٌ وُثّقت، كشفت، ثم أُلغي توثيقها. لا يجوز أن تُحرَم ممّا دفعت فيه.
    $company = CompanyProfile::factory()->create();

    $company->forceFill(['verification_status' => VerificationStatus::Pending])->save();

    $this->actingAs($company->user)->getJson('/api/v1/reveals')->assertOk();
});

it('★ 10 — مفتاح البوّابة يُطفئها من اللوحة بلا نشر كود', function () {
    EmployeeProfile::factory()->create();

    $company = CompanyProfile::factory()->pending()->create();

    $this->actingAs($company->user)->getJson('/api/v1/candidates')->assertForbidden();

    app(SettingsRepository::class)->set(SettingKeys::MODERATION_REQUIRE_COMPANY_VERIFICATION, false);

    $this->actingAs($company->user)->getJson('/api/v1/candidates')->assertOk();
});

// ═══ بوّابة الكفاءات: مطفأة افتراضاً ═══

it('★ 11 — الموظّف ينشر فيظهر فوراً — لا ينتظر مشرفاً', function () {
    $employee = EmployeeProfile::factory()->unpublished()->create([
        'title' => 'مهندس شبكات',
        'field_id' => Lookup::query()->group(Lookup::FIELD)->firstOrFail()->id,
        'city_id' => Lookup::query()->group(Lookup::CITY)->firstOrFail()->id,
        'employment_status_id' => Lookup::query()->group(Lookup::EMPLOYMENT_STATUS)->firstOrFail()->id,
        'years_experience' => 5,
        'summary' => 'نبذة كافية.',
    ]);

    $this->actingAs($employee->user)
        ->postJson('/api/v1/profile/publish')
        ->assertOk()
        ->assertJsonPath('approval_status', 'approved');

    // ويراه صاحبُ العمل من فوره.
    $refs = array_column(
        $this->actingAs(CompanyProfile::factory()->create()->user)
            ->getJson('/api/v1/candidates')->json('data'),
        'ref',
    );

    expect($refs)->toContain($employee->ref);
});

it('12 — ولا يُزعَج المشرف بإشعارٍ لا قرار فيه', function () {
    $admin = verifier();

    $employee = EmployeeProfile::factory()->unpublished()->create([
        'title' => 'مهندس',
        'field_id' => Lookup::query()->group(Lookup::FIELD)->firstOrFail()->id,
        'city_id' => Lookup::query()->group(Lookup::CITY)->firstOrFail()->id,
        'employment_status_id' => Lookup::query()->group(Lookup::EMPLOYMENT_STATUS)->firstOrFail()->id,
        'years_experience' => 5,
        'summary' => 'نبذة كافية.',
    ]);

    $this->actingAs($employee->user)->postJson('/api/v1/profile/publish')->assertOk();

    $keys = array_column(
        $this->actingAs($admin)->getJson('/api/v1/notifications')->json('data'),
        'key',
    );

    expect($keys)->not->toContain('admin.profile_submitted');
});
