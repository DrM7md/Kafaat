<?php

declare(strict_types=1);

use App\Enums\RevealStatus;
use App\Models\CandidateReveal;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use App\Support\Permissions;
use App\Support\Roles;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Validation\ValidationException;

/**
 * الاثنا عشر اختباراً الملزمة — Brain/06-masking-and-reveal.md §9.
 *
 * ★ لا تُعتمد مرحلة الحجب (م4) قبل مرورها جميعاً.
 *
 * لاحظ: نستعمل assertArrayNotHasKey لا assertNull. الحقل المحجوب
 * **غائب** من الاستجابة، لا فارغ فيها. ADR-003.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    app(SettingsRepository::class)->flush();
});

/**
 * الحقول التي يجب ألّا تظهر أبداً قبل كشفٍ صالح.
 *
 * `notice_period_clause` و`penalty_amount` أُضيفا بعد أن كشف فحصٌ حيٌّ
 * تسرّبهما: البند الجزائي يمنح الشركة ورقة تفاوضٍ مجّاناً ويكشف تفاصيل
 * عقد الموظّف مع صاحب عمله.
 */
const MASKED = [
    'name', 'initials', 'phone', 'email', 'current_employer', 'attachments',
    'notice_period_clause', 'penalty_amount',
];

function companyUser(): CompanyProfile
{
    return CompanyProfile::factory()->create();
}

it('1 — لا يُرسل الحقول المحجوبة في قائمة التصفّح', function () {
    $company = companyUser();
    EmployeeProfile::factory()->create();

    $response = $this->actingAs($company->user)->getJson('/api/v1/candidates');

    $response->assertOk();

    $candidate = $response->json('data.0');

    foreach (MASKED as $field) {
        expect($candidate)->not->toHaveKey($field, "الحقل «{$field}» تسرّب في التصفّح");
    }

    // ما يجب أن يظهر:
    expect($candidate)->toHaveKeys(['ref', 'title', 'years_experience', 'is_revealed', 'reveal_fee'])
        ->and($candidate['is_revealed'])->toBeFalse();
});

it('2 — لا يُرسل الحقول المحجوبة في الملفّ المفصّل', function () {
    $company = companyUser();
    $profile = EmployeeProfile::factory()->create();

    $data = $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertOk()
        ->json('data');

    foreach (MASKED as $field) {
        expect($data)->not->toHaveKey($field, "الحقل «{$field}» تسرّب في الملفّ المفصّل");
    }
});

it('3 — كشفٌ مكتمل يُظهر الاسم وبيانات التواصل بقيمها الصحيحة', function () {
    $company = companyUser();
    $profile = EmployeeProfile::factory()->create();

    CandidateReveal::factory()->completed()->create([
        'company_profile_id' => $company->id,
        'employee_profile_id' => $profile->id,
    ]);

    $data = $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertOk()
        ->json('data');

    expect($data)->toHaveKeys(['name', 'phone', 'email'])
        ->and($data['name'])->toBe($profile->user->name)
        ->and($data['phone'])->toBe($profile->user->phone)
        ->and($data['is_revealed'])->toBeTrue();
});

it('4 — كشفٌ pending لا يفكّ الحجب', function () {
    $company = companyUser();
    $profile = EmployeeProfile::factory()->create();

    CandidateReveal::factory()->create([
        'company_profile_id' => $company->id,
        'employee_profile_id' => $profile->id,
        'status' => RevealStatus::Pending,
    ]);

    $data = $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertOk()
        ->json('data');

    // المجموعة كاملة، لا `name` و`phone` فحسب: تسريبٌ موجَّه لـ `email` أو
    // `current_employer` في حالة `pending` كان يمرّ أخضر.
    foreach (MASKED as $field) {
        expect($data)->not->toHaveKey($field);
    }
});

it('5 — كشفٌ refunded يُعيد الحجب', function () {
    $company = companyUser();
    $profile = EmployeeProfile::factory()->create();

    CandidateReveal::factory()->create([
        'company_profile_id' => $company->id,
        'employee_profile_id' => $profile->id,
        'status' => RevealStatus::Refunded,
        'revealed_at' => now()->subDay(),
        'refunded_at' => now(),
    ]);

    $data = $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertOk()
        ->json('data');

    foreach (MASKED as $field) {
        expect($data)->not->toHaveKey($field);
    }
});

it('6 — ملفّ موظّف الشركة الطالبة يُعاد بـ 404 لا 403', function () {
    $company = companyUser();
    $profile = EmployeeProfile::factory()->employedBy($company->id)->create();

    // 403 تعني «موجود لكن ممنوع» — وهذا وحده يخبر الشركة أن موظّفها يبحث.
    $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertNotFound();
});

it('6ب — موظّف الشركة لا يظهر في قائمة تصفّحها أصلاً', function () {
    $company = companyUser();
    EmployeeProfile::factory()->employedBy($company->id)->create();
    EmployeeProfile::factory()->create();

    $data = $this->actingAs($company->user)->getJson('/api/v1/candidates')->json('data');

    // لو رشّحنا بعد العدّ لسرّبنا وجوده عبر فرق عدد النتائج.
    expect($data)->toHaveCount(1);
});

it('6ج — حتى الكشف المدفوع لا يخترق عمى صاحب العمل', function () {
    $company = companyUser();
    $profile = EmployeeProfile::factory()->employedBy($company->id)->create();

    CandidateReveal::factory()->completed()->create([
        'company_profile_id' => $company->id,
        'employee_profile_id' => $profile->id,
    ]);

    // المال لا يشتري كشف موظّفك الحالي.
    $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertNotFound();
});

it('7 — البحث بالاسم لا يُرجع صاحبه', function () {
    $company = companyUser();
    $profile = EmployeeProfile::factory()->create();
    $profile->user->update(['name' => 'أحمد الكواري']);

    // لو بحثنا في users.name لسرّبنا الاسم عبر الاستعلام:
    // ?q=أحمد يُرجع نتيجة ⇒ في القاعدة «أحمد».
    $data = $this->actingAs($company->user)
        ->getJson('/api/v1/candidates?q='.urlencode('أحمد'))
        ->assertOk()
        ->json('data');

    expect($data)->toBeEmpty();
});

it('8 — تعداد المعرّفات عشوائياً لا يصيب', function () {
    $company = companyUser();
    EmployeeProfile::factory()->count(3)->create();

    foreach (['1', '2', '00000001', 'AAAAAAAA'] as $guess) {
        $this->actingAs($company->user)
            ->getJson("/api/v1/candidates/{$guess}")
            ->assertNotFound();
    }

    // والمعرّف الحقيقي عشوائي بطول 8 من أبجدية بلا 0/O/1/I.
    expect(EmployeeProfile::first()->ref)->toMatch('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/');
});

it('10 — تعديل masking.fields ينعكس فوراً دون نشر كود', function () {
    $company = companyUser();
    EmployeeProfile::factory()->create();

    $settings = app(SettingsRepository::class);

    // نكشف «الراتب المتوقّع» بإزالته — ليس محجوباً أصلاً، فنحجبه بدلاً من ذلك.
    $settings->set('masking.fields', ['name', 'initials', 'phone', 'email', 'expected_salary'], confirmed: true);

    $candidate = $this->actingAs($company->user)->getJson('/api/v1/candidates')->json('data.0');

    expect($candidate)->not->toHaveKey('expected_salary')
        ->and($candidate)->toHaveKey('current_employer');   // لم يعد محجوباً
});

it('10ب — لا يُرفع الحجب عن الاسم دون تأكيد صريح', function () {
    $settings = app(SettingsRepository::class);

    expect(fn () => $settings->set('masking.fields', ['phone', 'email']))
        ->toThrow(ValidationException::class);

    // ومع التأكيد، يمرّ.
    $settings->set('masking.fields', ['phone', 'email'], confirmed: true);

    expect(settings('masking.fields'))->toBe(['phone', 'email']);
});

it('11 — تنزيل مرفق دون كشفٍ صالح مرفوض', function () {
    $company = companyUser();
    $profile = EmployeeProfile::factory()->create();

    $attachment = $profile->attachments()->create([
        'type' => 'cv',
        'disk' => 'private',
        'path' => 'attachments/x/cv.pdf',
        'original_name' => 'cv.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $this->actingAs($company->user)
        ->getJson("/api/v1/attachments/{$attachment->uuid}")
        ->assertForbidden();
});

it('12 — صلاحية الإشراف تكشف الحقول وتكتب أثر تدقيق', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole(Roles::CONTENT_MODERATOR);

    $profile = EmployeeProfile::factory()->create();

    expect($moderator->can(Permissions::PROFILE_MODERATE))->toBeTrue();

    $data = $this->actingAs($moderator)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertOk()
        ->json('data');

    expect($data)->toHaveKey('name');

    // من يرى المنتج مجّاناً يُسجَّل عليه ذلك.
    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $moderator->id,
        'action' => 'profile.viewed_as_moderator',
    ]);
});

it('★ ذاكرة الكشف لا تُسمَّم: تصفّحٌ محجوب ثم كشفٌ ثم قراءةٌ مكشوفة', function () {
    $company = companyUser();
    $profile = EmployeeProfile::factory()->create();

    // 1) قراءة تُهيّئ ذاكرة «لا كشف» لهذا الزوج.
    $this->actingAs($company->user)->getJson('/api/v1/candidates')->assertOk();
    $this->actingAs($company->user)->getJson("/api/v1/candidates/{$profile->ref}")->assertOk();

    // 2) يقع الكشف.
    CandidateReveal::factory()->completed()->create([
        'company_profile_id' => $company->id,
        'employee_profile_id' => $profile->id,
    ]);

    // 3) القراءة التالية يجب أن تراه مكشوفاً. ذاكرةٌ تكتب `false` ولا تُبطَل
    //    كانت تُبقي الحجب بعد الدفع — أسوأ عطبٍ ممكن في هذا المنتج.
    $data = $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertOk()
        ->json('data');

    expect($data)->toHaveKey('name')->and($data['is_revealed'])->toBeTrue();
});

it('غير المصادَق لا يصل إلى التصفّح إطلاقاً', function () {
    $this->getJson('/api/v1/candidates')->assertUnauthorized();
});

it('حساب موظّف لا يملك صلاحية التصفّح', function () {
    $employee = EmployeeProfile::factory()->create();

    $this->actingAs($employee->user)->getJson('/api/v1/candidates')->assertForbidden();
});
