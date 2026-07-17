<?php

declare(strict_types=1);

use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use App\Support\Roles;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;

/**
 * ★ مؤشّر الرغبة في الانتقال — شريطٌ يحرّكه الموظّف، والخادم يشتقّ منه الحالة.
 *
 * ولا يُرسَل الرقم للشركات أبداً: شارةٌ لا عدد.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(LookupSeeder::class);
    $this->seed(SettingSeeder::class);
    app(SettingsRepository::class)->flush();
});

function employeeUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Roles::EMPLOYEE);
    EmployeeProfile::factory()->for($user)->create();

    return $user;
}

function statusKeyOf(EmployeeProfile $profile): ?string
{
    return Lookup::query()->find($profile->employment_status_id)?->key;
}

it('يشتقّ الحالة من النسبة — لا يقبلها من العميل', function (int $readiness, string $expected) {
    $user = employeeUser();

    $this->actingAs($user)
        ->putJson('/api/v1/profile', ['readiness' => $readiness])
        ->assertOk();

    expect(statusKeyOf($user->employeeProfile->fresh()))->toBe($expected);
})->with([
    'صفر ⇒ مستقرّ' => [0, 'stable'],
    'دون العتبة ⇒ مستقرّ' => [30, 'stable'],
    'عند عتبة الانفتاح' => [35, 'open'],
    'بين العتبتين ⇒ منفتح' => [60, 'open'],
    'عند عتبة الترك' => [70, 'leaving'],
    'أقصاها ⇒ راغب في الترك' => [100, 'leaving'],
]);

/**
 * ★★ التناقض المستحيل.
 *
 * لو حسبت الواجهةُ الحالة لأمكن أن تصل نسبة 95 وحالة «مستقرّ» معاً — فيظهر
 * الموظّف مستقرّاً في الشارة، وفي مقدّمة فرز «الراغبين في الانتقال» في آنٍ
 * واحد. الاشتقاق في الخادم يجعل هذا مستحيلاً لا مستبعَداً.
 */
it('يتجاهل الحالة التي يرسلها العميل حين تُرسَل النسبة معها', function () {
    $user = employeeUser();
    $stable = Lookup::query()->where('group', Lookup::EMPLOYMENT_STATUS)->where('key', 'stable')->firstOrFail();

    $this->actingAs($user)
        ->putJson('/api/v1/profile', [
            'readiness' => 95,
            'employment_status_id' => $stable->id,   // كذبةٌ صريحة
        ])
        ->assertOk();

    expect(statusKeyOf($user->employeeProfile->fresh()))->toBe('leaving');
});

it('يختم الحالة بتاريخ اليوم كلّما ضُبطت', function () {
    $user = employeeUser();

    expect($user->employeeProfile->readiness_confirmed_at)->toBeNull();

    $this->actingAs($user)->putJson('/api/v1/profile', ['readiness' => 50])->assertOk();

    expect($user->employeeProfile->fresh()->readiness_confirmed_at)->not->toBeNull();
});

/**
 * ★ أرخص فعلٍ في المنصّة وأثمن ما فيها.
 *
 * بلا هذا المسار لا سبيل لتجديد الإشارة إلّا بفتح النموذج كلّه وحفظه —
 * احتكاكٌ يكفي ليهجره الناس، فتشيخ كلُّ الحالات، ويدفع المشتري في بياناتٍ ميتة.
 */
it('يُجدّد ختم الحالة بنقرة واحدة دون تغيير النسبة', function () {
    $user = employeeUser();

    $user->employeeProfile->forceFill([
        'readiness' => 80,
        'readiness_confirmed_at' => now()->subDays(60),
    ])->save();

    $this->actingAs($user)->postJson('/api/v1/profile/readiness/confirm')->assertOk();

    $profile = $user->employeeProfile->fresh();

    expect($profile->readiness)->toBe(80)
        ->and($profile->readinessAgeDays())->toBe(0)
        ->and(statusKeyOf($profile))->toBe('leaving');
});

it('يرفض التجديد لمن لم يضبط مؤشّره قطّ', function () {
    $this->actingAs(employeeUser())
        ->postJson('/api/v1/profile/readiness/confirm')
        ->assertStatus(422);
});

/**
 * ★★ شارةٌ لا عدد — كشارة الطلب تماماً.
 *
 * إرسال «87/100» يوهم الشركةَ بدقّةٍ لا وجود لها، ويصِم الموظّف برقمٍ يُقاس به.
 */
it('لا يُرسل النسبة للشركات أبداً — الشارة وعمرُها فقط', function () {
    $company = CompanyProfile::factory()->create();
    $profile = EmployeeProfile::factory()->create();

    $profile->forceFill(['readiness' => 87, 'readiness_confirmed_at' => now()->subDays(3)])->save();

    $candidate = $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertOk()
        ->json('data');

    expect($candidate)->not->toHaveKey('readiness')
        ->and($candidate)->not->toHaveKey('readiness_confirmed_at')
        ->and($candidate['status_age_days'])->toBe(3)
        ->and($candidate['status_stale'])->toBeFalse();
});

it('يُعلن بيات الحالة بعد عتبة الشيخوخة', function () {
    $company = CompanyProfile::factory()->create();
    $profile = EmployeeProfile::factory()->create();

    // 90 يوماً هي العتبة المبذورة (readiness.stale_days).
    $profile->forceFill(['readiness' => 90, 'readiness_confirmed_at' => now()->subDays(120)])->save();

    $candidate = $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertOk()
        ->json('data');

    expect($candidate['status_stale'])->toBeTrue();
});

/**
 * ★ العهد المقطوع في محرّك الإعدادات: حالةٌ رابعة بعتبتها ولونها — بلا كود.
 */
it('يحترم حالةً رابعة يضيفها المشرف بعتبتها من اللوحة', function () {
    // status عبر forceFill كما في Admin\LookupController::store — خارج fillable.
    Lookup::make([
        'group' => Lookup::EMPLOYMENT_STATUS,
        'key' => 'urgent',
        'label_ar' => 'يبحث بجدّية',
        'sort_order' => 0,
        'is_active' => true,
        'meta' => ['bg' => '#FBE9E9', 'color' => '#8a2b2b', 'dot' => '#C83C3C', 'readiness_min' => 90],
    ])->forceFill(['status' => Lookup::STATUS_APPROVED])->save();

    $user = employeeUser();

    $this->actingAs($user)->putJson('/api/v1/profile', ['readiness' => 95])->assertOk();

    expect(statusKeyOf($user->employeeProfile->fresh()))->toBe('urgent');
});
