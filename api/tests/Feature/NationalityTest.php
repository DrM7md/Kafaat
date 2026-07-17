<?php

declare(strict_types=1);

use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;

/**
 * الجنسية — صفةٌ مهنية مكشوفة، ومِصفاةٌ في التصفّح.
 *
 * ★ مكشوفة كالمدينة والمجال لا محجوبة كالاسم: الشركة تُصفّي بها بحثها
 *   (تأشيرة، توطين)، ولا تدلّ على صاحبها. لو حُجبت لصارت المِصفاةُ قناةَ
 *   استكشافٍ لا بحثاً — تسأل عنها بالفلتر فتعرف ما لا يُعرَض.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(LookupSeeder::class);
});

it('1 — الجنسية تصل في بطاقة التصفّح وفي الملفّ المفصّل', function () {
    $company = CompanyProfile::factory()->create();
    $profile = EmployeeProfile::factory()->withNationality('qa')->create();

    $card = $this->actingAs($company->user)->getJson('/api/v1/candidates')->json('data.0');

    expect($card['nationality']['label'])->toBe('قطري');

    $detail = $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->json('data');

    expect($detail['nationality']['label'])->toBe('قطري');
});

it('★ 2 — التصفية بالجنسية لا تُرجع غيرها', function () {
    $company = CompanyProfile::factory()->create();

    EmployeeProfile::factory()->withNationality('qa')->create();
    EmployeeProfile::factory()->withNationality('in')->create();
    EmployeeProfile::factory()->create(); // بلا جنسية — لا تُطابق أيّ مِصفاة

    $response = $this->actingAs($company->user)
        ->getJson('/api/v1/candidates?nationality=qa')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.nationality.key'))->toBe('qa');

    // بلا مِصفاة: الثلاثة كلّهم — ومن لا جنسية له لا يختفي من التصفّح.
    expect($this->actingAs($company->user)->getJson('/api/v1/candidates')->json('meta.total'))->toBe(3);
});

it('3 — الموظّف يحفظ جنسيته في ملفّه', function () {
    $profile = EmployeeProfile::factory()->create();

    $indian = Lookup::query()
        ->where('group', Lookup::NATIONALITY)
        ->where('key', 'in')
        ->firstOrFail();

    $this->actingAs($profile->user)
        ->putJson('/api/v1/profile', ['nationality_id' => $indian->id])
        ->assertOk();

    expect($profile->refresh()->nationality_id)->toBe($indian->id);
});

it('★★ 4 — خيارات المِصفاة من المسجَّلين فعلاً، لا من الكتالوج', function () {
    $company = CompanyProfile::factory()->create();

    EmployeeProfile::factory()->withNationality('qa')->create();
    EmployeeProfile::factory()->withNationality('in')->create();
    EmployeeProfile::factory()->withNationality('in')->create(); // مكرّرة — تظهر مرّة
    EmployeeProfile::factory()->create();                        // بلا جنسية

    $facet = $this->actingAs($company->user)
        ->getJson('/api/v1/candidates')
        ->json('facets.nationality');

    // ★ الكتالوج فيه أكثر من مئة، والمعروض اثنتان: خيارٌ لا نتيجةَ له وعدٌ كاذب.
    expect($facet)->toHaveCount(2)
        ->and(collect($facet)->pluck('key')->sort()->values()->all())->toBe(['in', 'qa']);
});

it('★ 5 — اختيار جنسيةٍ لا يُفرغ قائمة الخيارات', function () {
    $company = CompanyProfile::factory()->create();

    EmployeeProfile::factory()->withNationality('qa')->create();
    EmployeeProfile::factory()->withNationality('in')->create();

    // لو حُسبت الخيارات بعد المِصفاة لبقي المختار وحده — فلا سبيل إلى تبديله.
    $facet = $this->actingAs($company->user)
        ->getJson('/api/v1/candidates?nationality=qa')
        ->json('facets.nationality');

    expect($facet)->toHaveCount(2);
});

it('6 — الموظّف يقترح جنسيةً غير مدرجة، فتُعلَّق حتى يعتمدها مشرف', function () {
    $profile = EmployeeProfile::factory()->create();

    $this->actingAs($profile->user)
        ->putJson('/api/v1/profile', ['nationality_other' => 'أندوري'])
        ->assertOk();

    $suggested = Lookup::query()
        ->where('group', Lookup::NATIONALITY)
        ->where('label_ar', 'أندوري')
        ->firstOrFail();

    expect($suggested->status)->toBe(Lookup::STATUS_PENDING)
        ->and($suggested->is_active)->toBeFalse()
        ->and($profile->refresh()->nationality_id)->toBe($suggested->id);

    // لا تتسرّب إلى قائمة الجميع قبل الاعتماد.
    $lookups = $this->actingAs($profile->user)->getJson('/api/v1/lookups')->json('lookups.nationality');

    expect(collect($lookups)->pluck('label')->all())->not->toContain('أندوري');
});

it('7 — الجنسية مجموعةٌ مستقلّة عن الدول', function () {
    // ستّ دولٍ نعمل فيها، وثلاثون جنسيةً تعمل عندنا. خلطُهما يجعل الموظّف
    // الهندي بلا خيارٍ يصف نفسه به.
    $countries = Lookup::query()->where('group', Lookup::COUNTRY)->count();
    $nationalities = Lookup::query()->where('group', Lookup::NATIONALITY)->count();

    expect($nationalities)->toBeGreaterThan($countries);
});
