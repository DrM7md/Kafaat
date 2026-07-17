<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Models\User;
use App\Support\Roles;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * صفحة «حسابي» — ما يملكه المستخدم عن نفسه.
 *
 * ★★ الخطر الأوّل: أن يُرقّي المستخدم نفسه بطلبٍ واحد. `type` و`status`
 *    وحالة التوثيق ليست في `UpdateAccountRequest` — واختبارٌ يحرس ذلك، لأن
 *    إضافة حقلٍ إلى `fillable` يوماً ما تكفي لفتح الباب.
 *
 * ★★ الخطر الثاني: شركةٌ موثَّقة تبدّل رقم سجلّها فيبقى الختم. المشرف وثّق
 *    سجلّاً بعينه — والتوثيق يسقط تلقائياً عند تبديله.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(LookupSeeder::class);
});

/** جسمٌ صالحٌ كاملٌ لشركة — الاختبار يبدّل منه ما يعنيه. */
function companyPayload(CompanyProfile $company, array $overrides = []): array
{
    return [...[
        'name' => $company->user->name,
        'email' => $company->user->email,
        'phone' => '55512345',
        'legal_name' => $company->legal_name,
        'cr_number' => $company->cr_number,
    ], ...$overrides];
}

it('1 — الموظّف يعدّل اسمه وهاتفه وبريده', function () {
    $profile = EmployeeProfile::factory()->create();

    $this->actingAs($profile->user)
        ->putJson('/api/v1/me', [
            'name' => 'محمّد الكواري',
            'email' => 'mohammed@kafaat.qa',
            'phone' => '55598765',
        ])
        ->assertOk()
        ->assertJsonPath('user.name', 'محمّد الكواري');

    expect($profile->user->refresh())
        ->email->toBe('mohammed@kafaat.qa')
        ->phone->toBe('55598765');
});

it('★ 2 — الشركة لا تحفظ حسابها بلا رقم سجلّ تجاري', function () {
    $company = CompanyProfile::factory()->create();

    $this->actingAs($company->user)
        ->putJson('/api/v1/me', companyPayload($company, ['cr_number' => '']))
        ->assertJsonValidationErrors('cr_number');

    // ولا بحذف المفتاح أصلاً — «غائب» ليس بابَ التفافٍ على «مطلوب».
    $body = companyPayload($company);
    unset($body['cr_number']);

    $this->actingAs($company->user)
        ->putJson('/api/v1/me', $body)
        ->assertJsonValidationErrors('cr_number');
});

it('3 — الموظّف يحفظ حسابه بلا حقول شركة', function () {
    $profile = EmployeeProfile::factory()->create();

    // `cr_number` مطلوبٌ لمن له ملفّ شركة **وحده**. غيابه هنا ليس خطأً.
    $this->actingAs($profile->user)
        ->putJson('/api/v1/me', ['name' => 'سارة', 'email' => $profile->user->email, 'phone' => null])
        ->assertOk();
});

it('★★ 4 — تبديل رقم السجلّ يُسقط توثيق الشركة', function () {
    $company = CompanyProfile::factory()->create();

    $company->forceFill([
        'verification_status' => VerificationStatus::Verified,
        'verified_at' => now(),
    ])->save();

    $this->actingAs($company->user)
        ->putJson('/api/v1/me', companyPayload($company, ['cr_number' => '9999999999']))
        ->assertOk();

    expect($company->refresh())
        ->cr_number->toBe('9999999999')
        ->verification_status->toBe(VerificationStatus::Pending)
        ->verified_at->toBeNull();
});

it('5 — حفظٌ لا يمسّ رقم السجلّ يُبقي التوثيق قائماً', function () {
    $company = CompanyProfile::factory()->create();

    $company->forceFill([
        'verification_status' => VerificationStatus::Verified,
        'verified_at' => now(),
    ])->save();

    $this->actingAs($company->user)
        ->putJson('/api/v1/me', companyPayload($company, ['legal_name' => 'اسمٌ قانونيٌّ جديد']))
        ->assertOk();

    expect($company->refresh())->verification_status->toBe(VerificationStatus::Verified);
});

it('★★ 6 — لا يُرقّي المستخدم نفسه بحقلٍ في الجسم', function () {
    // شركةٌ تنتظر التوثيق — وهي الحالة التي تُغري صاحبَها بترقية نفسه، إذ
    // بينه وبين تصفّح الكفاءات حقلٌ واحد.
    $company = CompanyProfile::factory()->pending()->create();

    $this->actingAs($company->user)
        ->putJson('/api/v1/me', companyPayload($company, [
            'type' => 'admin',
            'status' => 'suspended',
            'verification_status' => 'verified',
        ]))
        ->assertOk();

    expect($company->user->refresh()->type->value)->toBe('company')
        ->and($company->user->refresh()->status->value)->toBe('active')
        ->and($company->refresh()->verification_status)->toBe(VerificationStatus::Pending);
});

it('7 — البريد المسجَّل لغيره مرفوض، وبريد صاحبه نفسه مقبول', function () {
    $company = CompanyProfile::factory()->create();
    $other = User::factory()->create(['email' => 'taken@kafaat.qa']);

    $this->actingAs($company->user)
        ->putJson('/api/v1/me', companyPayload($company, ['email' => $other->email]))
        ->assertJsonValidationErrors('email');

    // بريدُه هو: حفظُ نموذجٍ لم يُغيَّر فيه البريد يجب ألّا يُخفق.
    $this->actingAs($company->user)
        ->putJson('/api/v1/me', companyPayload($company))
        ->assertOk();
});

it('8 — مجال الشركة وحجمها يُحفظان من القوائم المرجعية', function () {
    $company = CompanyProfile::factory()->create();

    $industry = Lookup::query()->where('group', Lookup::INDUSTRY)->firstOrFail();
    $size = Lookup::query()->where('group', Lookup::COMPANY_SIZE)->firstOrFail();

    $this->actingAs($company->user)
        ->putJson('/api/v1/me', companyPayload($company, [
            'industry_id' => $industry->id,
            'size_id' => $size->id,
            'brand_name' => 'كفاءات',
        ]))
        ->assertOk();

    expect($company->refresh())
        ->industry_id->toBe($industry->id)
        ->brand_name->toBe('كفاءات');

    // مجالٌ من مجموعةٍ أخرى (مدينة مثلاً) ليس مجالاً — والقيد على المجموعة لا على الوجود.
    $city = Lookup::query()->where('group', Lookup::CITY)->firstOrFail();

    $this->actingAs($company->user)
        ->putJson('/api/v1/me', companyPayload($company, ['industry_id' => $city->id]))
        ->assertJsonValidationErrors('industry_id');
});

it('★ 9 — تغيير كلمة المرور يلزمه معرفةُ القديمة', function () {
    $user = User::factory()->create(['password' => 'OldPassword123!']);
    $user->assignRole(Roles::EMPLOYEE);

    $this->actingAs($user)
        ->putJson('/api/v1/me/password', [
            'current_password' => 'WrongGuess123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertJsonValidationErrors('current_password');

    $this->actingAs($user)
        ->putJson('/api/v1/me/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertOk();

    expect(Hash::check('NewPassword123!', $user->refresh()->password))->toBeTrue();
});

it('10 — التسجيل بحساب شركة يرفض بلا رقم سجلّ', function () {
    $this->postJson('/api/v1/auth/register/company', [
        'name' => 'مسؤول',
        'legal_name' => 'شركة بلا سجلّ',
        'email' => 'no-cr@kafaat.qa',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'accept_terms' => true,
        'accept_privacy' => true,
    ])->assertJsonValidationErrors('cr_number');
});
