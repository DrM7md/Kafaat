<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use App\Support\SettingKeys;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * العطب يُغلق الباب، لا يفتحه.
 *
 * ★ هذه الاختبارات تحرس السلوك عند **غياب** الإعداد أو تلفه — لا عند صحّته.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(LookupSeeder::class);
    $this->seed(SettingSeeder::class);
    app(SettingsRepository::class)->flush();
});

/**
 * ملفّ يتجاوز عتبة الاكتمال (60%): المسمّى والمجال والمدينة وحالة التوظيف
 * وسنوات الخبرة والنبذة = 6 من 8. بلا هذه الحقول يرفض النشرُ الملفَّ بـ 422،
 * فيختبئ العطب الحقيقي خلف رفضٍ مشروع.
 *
 * @return array<string, int>
 */
function completeProfileFields(): array
{
    $id = fn (string $group): int => (int) Lookup::query()->where('group', $group)->value('id');

    return [
        'field_id' => $id(Lookup::FIELD),
        'city_id' => $id(Lookup::CITY),
        'employment_status_id' => $id(Lookup::EMPLOYMENT_STATUS),
    ];
}

it('★ غياب صفّ masking.fields لا يكشف شيئاً', function () {
    // ★ العلّة: الافتراضي كان `[]` — أي «لا تحجب شيئاً». صفٌّ مفقود من
    //   القاعدة (أو كاشٌ بائت) كان يكشف كل اسمٍ وهاتفٍ وبريد بلا دفع.
    Setting::query()->where('key', SettingKeys::MASKING_FIELDS)->delete();
    app(SettingsRepository::class)->flush();

    expect(settings(SettingKeys::MASKING_FIELDS))->toBeNull();

    $company = CompanyProfile::factory()->create();
    $profile = EmployeeProfile::factory()->create();

    $data = $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertOk()
        ->json('data');

    foreach (SettingKeys::defaultMaskedFields() as $field) {
        expect($data)->not->toHaveKey($field);
    }
});

it('★ البذرة تُبطل كاش الإعدادات — لا تُبذَر قيمةٌ لا يراها أحد', function () {
    // ★ العلّة: `Cache::rememberForever` + بذرةٌ لا تُبطل الكاش = قيمةٌ في
    //   القاعدة لا تصل الكود أبداً.
    app(SettingsRepository::class)->set(SettingKeys::REVEAL_FEE, 999);
    expect(settings(SettingKeys::REVEAL_FEE))->toBe(999);

    // تعديلٌ من خارج المستودع (هجرة، صيانة يدوية) ثم إعادة بذر.
    DB::table('settings')->where('key', SettingKeys::REVEAL_FEE)->delete();
    $this->seed(SettingSeeder::class);

    expect(settings(SettingKeys::REVEAL_FEE))->toBe(75);
});

it('الإعداد الفارغ صراحةً يُحترَم — الغياب وحده يُغلق الباب', function () {
    // `[]` مكتوبةٌ بتأكيدٍ مزدوج تعني «لا تحجب»، وتختلف عن المفتاح الغائب.
    app(SettingsRepository::class)->set(SettingKeys::MASKING_FIELDS, [], confirmed: true);

    $company = CompanyProfile::factory()->create();
    $profile = EmployeeProfile::factory()->create();

    $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertOk()
        ->assertJsonPath('data.name', $profile->user->name);
});

// ─────────────────────────────────────────────────────────────
// أنواع الإعدادات تُتحقَّق عند الكتابة
// ─────────────────────────────────────────────────────────────

it('★ إعدادٌ منطقيّ لا يُطفَأ بنصّ "false"', function () {
    // ★ العلّة: `(bool) "false" === true`. إطفاءُ عمى صاحب العمل بنصٍّ
    //   خاطئ كان **يُشعله**، والمشرف يظنّه مطفأً.
    expect(fn () => app(SettingsRepository::class)->set(SettingKeys::PRIVACY_EMPLOYER_BLIND, 'false'))
        ->toThrow(ValidationException::class);

    expect(settings(SettingKeys::PRIVACY_EMPLOYER_BLIND))->toBeTrue();
});

it('★ رسم الكشف لا يصير صفراً بنصٍّ غير رقمي', function () {
    expect(fn () => app(SettingsRepository::class)->set(SettingKeys::REVEAL_FEE, 'مجاناً'))
        ->toThrow(ValidationException::class);

    expect(settings(SettingKeys::REVEAL_FEE))->toBe(75);
});

it('يقبل النوع الصحيح', function () {
    $settings = app(SettingsRepository::class);

    $settings->set(SettingKeys::PRIVACY_EMPLOYER_BLIND, false);
    $settings->set(SettingKeys::REVEAL_FEE, 120);

    expect(settings(SettingKeys::PRIVACY_EMPLOYER_BLIND))->toBeFalse()
        ->and(settings(SettingKeys::REVEAL_FEE))->toBe(120);
});

// ─────────────────────────────────────────────────────────────
// النشر — المسار الذي لم يكن مُختبَراً إطلاقاً
// ─────────────────────────────────────────────────────────────

it('★ النشر يكتب published_at فعلاً — لا يُسقطه حارس التعبئة', function () {
    // ★ العلّة: `update(['published_at' => ...])` وهو ليس في `#[Fillable]`.
    //   في الإنتاج يُسقَط صامتاً فتُعاد «نُشر ملفّك» ولا يُنشر شيء.
    $profile = EmployeeProfile::factory()->unpublished()->create(completeProfileFields());

    $this->actingAs($profile->user)
        ->postJson('/api/v1/profile/publish')
        ->assertOk();

    $profile->refresh();

    // `Approved` لا `Pending`: بوّابة الكفاءات مطفأة افتراضاً — الموظّف ينشر
    // فيظهر فوراً. المُختبَر هنا أنّ `published_at` تُكتب فعلاً، لا حالةُ
    // الاعتماد. ودورةُ الاعتماد نفسها تُختبَر في AdminPanelTest بمفتاحها مُشعَلاً.
    expect($profile->published_at)->not->toBeNull()
        ->and($profile->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($profile->submitted_at)->not->toBeNull();
});

it('السحب يُعيد الملفّ مسودّةً', function () {
    $profile = EmployeeProfile::factory()->create(['approval_status' => ApprovalStatus::Approved]);

    $this->actingAs($profile->user)
        ->postJson('/api/v1/profile/unpublish')
        ->assertOk();

    $profile->refresh();

    expect($profile->published_at)->toBeNull()
        ->and($profile->approval_status)->toBe(ApprovalStatus::Draft);
});

// ─────────────────────────────────────────────────────────────
// الأداء: استعلامات الكشف لا تتناسب مع حجم الصفحة
// ─────────────────────────────────────────────────────────────

it('★ قائمة التصفّح: عدد الاستعلامات ثابت مهما كثر المرشّحون', function () {
    // ★ العلّة: `hasValidReveal()` استعلامٌ لكل مرشّح داخل الـ Resource.
    $company = CompanyProfile::factory()->create();
    EmployeeProfile::factory()->count(12)->create();

    // إحماء: أوّل طلبٍ يملأ كاش الصلاحيات والإعدادات، فلا يُحسب فرقه.
    $this->actingAs($company->user)->getJson('/api/v1/candidates?per_page=1')->assertOk();

    DB::enableQueryLog();
    $this->actingAs($company->user)->getJson('/api/v1/candidates?per_page=3')->assertOk();
    $few = count(DB::getQueryLog());

    DB::flushQueryLog();
    $this->actingAs($company->user)->getJson('/api/v1/candidates?per_page=12')->assertOk();
    $many = count(DB::getQueryLog());
    DB::disableQueryLog();

    // 12 مرشّحاً بدل 3 يجب ألّا يُضيف تسعة استعلامات.
    expect($many)->toBe($few);
});

it('★ مشرف يتصفّح القائمة: صفّ تدقيقٍ واحد لا صفٌّ لكل مرشّح', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('profile.moderate', 'candidate.browse');

    EmployeeProfile::factory()->count(5)->create();

    $this->actingAs($admin)->getJson('/api/v1/candidates?per_page=5')->assertOk();

    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'profile.listed_as_moderator']);
});

it('★ مشرف يفتح ملفّاً: صفّ تدقيقٍ واحد لا صفّان', function () {
    // ★ العلّة: `visibleFields()` كانت تُسجّل، وتُستدعى مرّتين لكل معاينة.
    $admin = User::factory()->create();
    $admin->givePermissionTo('profile.moderate');

    $profile = EmployeeProfile::factory()->create();

    $this->actingAs($admin)->getJson("/api/v1/candidates/{$profile->ref}")->assertOk();

    expect(DB::table('audit_logs')->where('action', 'profile.viewed_as_moderator')->count())->toBe(1);
});

// ─────────────────────────────────────────────────────────────
// البحث: محارف البدل تُعامَل حرفياً
// ─────────────────────────────────────────────────────────────

it('★ ?q=% لا يُطابق كل شيء', function () {
    $company = CompanyProfile::factory()->create();
    EmployeeProfile::factory()->count(3)->create();

    $data = $this->actingAs($company->user)
        ->getJson('/api/v1/candidates?q=%')
        ->assertOk()
        ->json('data');

    expect($data)->toBeEmpty();
});
