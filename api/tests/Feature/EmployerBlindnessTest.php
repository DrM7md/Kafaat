<?php

declare(strict_types=1);

use App\Enums\RevealStatus;
use App\Models\CandidateReveal;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Services\Settings\SettingsRepository;
use App\Support\EmployerName;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;

/**
 * ★ عمى صاحب العمل (ADR-004) — الشركة لا ترى ملفّ موظّفها الحالي.
 *
 * آلة الحجب كانت مبنيّةً بالكامل، ومعطَّلةً بالكامل: لا شيء في المنصّة كان
 * يملأ `current_employer_company_id`. الموظّف يكتب اسم شركته نصّاً حرّاً،
 * فيبقى الحقل `NULL`، ويظهر الموظّف لشركته في أوّل صفحةٍ تتصفّحها.
 *
 * هذه الاختبارات تحرس **الوصلة** لا الآلة: أن يمتلئ الحقل فعلاً، وأن يمتلئ
 * حتى حين لا يختار الموظّف شركته من قائمة.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(LookupSeeder::class);
    $this->seed(SettingSeeder::class);
    app(SettingsRepository::class)->flush();
});

it('يحجب الموظّف عن شركته حين يختارها من القائمة', function () {
    $employer = CompanyProfile::factory()->create();
    $mine = EmployeeProfile::factory()->create(['current_employer_company_id' => $employer->id]);
    $other = EmployeeProfile::factory()->create();

    $refs = $this->actingAs($employer->user)
        ->getJson('/api/v1/candidates')
        ->assertOk()
        ->json('data.*.ref');

    expect($refs)->not->toContain($mine->ref)
        ->and($refs)->toContain($other->ref);
});

/**
 * ★★ الحالة الغالبة، وهي التي كانت مكشوفة تماماً.
 *
 * الموظّفون يسبقون الشركات إلى المنصّة. فيكتب الموظّف اسم شركته نصّاً وهي
 * غير مسجَّلة بعد، ثمّ تسجّل شركتُه بعد شهر — فتراه.
 */
it('يربط الموظّف بشركته يوم تسجّل هي، ولو كتب اسمها قبل وجودها', function () {
    $employee = EmployeeProfile::factory()->create([
        'current_employer_name' => 'شركة الخليج للتقنية',
        'current_employer_company_id' => null,
    ]);

    expect($employee->current_employer_company_id)->toBeNull();

    // الآن تسجّل الشركة — باسمٍ قانونيّ مختلفٍ في صياغته، متطابقٍ في مسمّاه.
    $employer = CompanyProfile::factory()->create(['legal_name' => 'الخليج للتقنية ذ.م.م']);

    expect($employee->fresh()->current_employer_company_id)->toBe($employer->id);

    $refs = $this->actingAs($employer->user)
        ->getJson('/api/v1/candidates')
        ->assertOk()
        ->json('data.*.ref');

    expect($refs)->not->toContain($employee->ref);
});

it('يربط الموظّف بشركته المسجَّلة أصلاً حين يكتب اسمها نصّاً حرّاً', function () {
    $employer = CompanyProfile::factory()->create(['legal_name' => 'مجموعة قطر للإنشاءات']);

    $employee = EmployeeProfile::factory()->create([
        'current_employer_name' => 'قطر للانشاءات',   // بلا همزة، وبلا «مجموعة»
    ]);

    expect($employee->current_employer_company_id)->toBe($employer->id);
});

it('يحجب بالاسم المطبَّع ولو لم يُربَط المعرّف', function () {
    $employer = CompanyProfile::factory()->create(['legal_name' => 'الخليج للتقنية']);
    $employee = EmployeeProfile::factory()->create();

    // نكسر الوصلة عمداً — نُحاكي صفّاً كتبته هجرةٌ قديمة أو بذرةٌ لا تمرّ
    // بخطّاف النموذج. الحجب يجب أن يصمد على الاسم وحده.
    DB::table('employee_profiles')->where('id', $employee->id)->update([
        'current_employer_company_id' => null,
        'current_employer_name' => 'شركة الخليج للتقنية ذ.م.م',
        'current_employer_name_key' => EmployerName::key('شركة الخليج للتقنية ذ.م.م'),
    ]);

    $refs = $this->actingAs($employer->user)
        ->getJson('/api/v1/candidates')
        ->assertOk()
        ->json('data.*.ref');

    expect($refs)->not->toContain($employee->ref);

    // ولا بالرابط المباشر: الوجود نفسه سرّ — 404 لا 403.
    $this->actingAs($employer->user)
        ->getJson("/api/v1/candidates/{$employee->ref}")
        ->assertNotFound();
});

/**
 * ⚠️ الفخّ الذي يبتلع الجميع.
 *
 * `NULL NOT IN (…)` تساوي `NULL` لا `TRUE` في SQL. فلو كُتب شرطُ الاسم بلا
 * `whereNull` صريحة لأسقط الاستعلامُ **كلَّ** من لم يذكر جهة عمله — أي أكثر
 * الملفّات. الحجب كان سيبتلع المنصّة كلّها بصمت.
 */
it('لا يبتلع من لم يذكر جهة عمله أصلاً', function () {
    $company = CompanyProfile::factory()->create();
    $employee = EmployeeProfile::factory()->create([
        'current_employer_name' => null,
        'current_employer_company_id' => null,
    ]);

    $refs = $this->actingAs($company->user)
        ->getJson('/api/v1/candidates')
        ->assertOk()
        ->json('data.*.ref');

    expect($refs)->toContain($employee->ref);
});

it('لا يشتري المالُ كشفَ موظّفك: الكشف المدفوع لا يخترق العمى', function () {
    $employer = CompanyProfile::factory()->create();
    $employee = EmployeeProfile::factory()->create(['current_employer_company_id' => $employer->id]);

    // كشفٌ مكتمل ومدفوع — ومع ذلك 404.
    CandidateReveal::factory()->create([
        'company_profile_id' => $employer->id,
        'employee_profile_id' => $employee->id,
        'status' => RevealStatus::Completed,
    ]);

    $this->actingAs($employer->user)
        ->getJson("/api/v1/candidates/{$employee->ref}")
        ->assertNotFound();
});

it('لا تسرق شركةٌ موظّفاً ربط نفسه بشركةٍ أخرى صراحةً', function () {
    $chosen = CompanyProfile::factory()->create(['legal_name' => 'الشركة التي اخترتها']);

    $employee = EmployeeProfile::factory()->create([
        'current_employer_company_id' => $chosen->id,
    ]);

    // شركةٌ أخرى تسجّل باسمٍ يُطابق ما كتبه الموظّف — لا يجوز أن تنتزعه.
    $intruder = CompanyProfile::factory()->create(['legal_name' => 'شركة أخرى تماماً']);
    $intruder->claimCurrentEmployees();

    expect($employee->fresh()->current_employer_company_id)->toBe($chosen->id);
});

/**
 * ★★ الثغرة التي كشفها التحقّق الحيّ — ولم تكشفها الاختبارات.
 *
 * شركةٌ واحدة مسجَّلة مرّتين بصيغتين («الخليج للتقنية ذ.م.م» و«شركة الخليج
 * للتقنية») — وهو واقعٌ لا فرض: فرعان أو مديران يسجّلان بصيغتين.
 *
 * كان الاختيار الصريح من القائمة **أقلّ حمايةً** من الكتابة الحرّة: من كتب
 * الاسم نصّاً حُجب عن التوأمين معاً، ومن اختار إحداهما من القائمة بقي مفتاحُ
 * اسمه فارغاً — فانكشف للأخرى. عكسَ المقصود تماماً.
 */
it('يحجبه عن توأم شركته أيضاً حين يختارها من القائمة', function () {
    $chosen = CompanyProfile::factory()->create(['legal_name' => 'الخليج للتقنية ذ.م.م']);
    $twin = CompanyProfile::factory()->create(['legal_name' => 'شركة الخليج للتقنية']);

    // المفتاحان متطابقان — هي هي.
    expect($twin->legal_name_key)->toBe($chosen->legal_name_key);

    $employee = EmployeeProfile::factory()->create([
        'current_employer_company_id' => $chosen->id,
    ]);

    foreach ([$chosen, $twin] as $company) {
        $refs = $this->actingAs($company->user)
            ->getJson('/api/v1/candidates')
            ->assertOk()
            ->json('data.*.ref');

        expect($refs)->not->toContain($employee->ref, "الملفّ ظهر لـ «{$company->legal_name}»");

        $this->actingAs($company->user)
            ->getJson("/api/v1/candidates/{$employee->ref}")
            ->assertNotFound();
    }
});

it('يُطبّع اسم الشركة فيُسقط الصيغة القانونية والهمزات والمسافات', function () {
    $key = EmployerName::key('شركة الخليج للتقنية ذ.م.م');

    expect(EmployerName::key('الخليج للتقنية'))->toBe($key)
        ->and(EmployerName::key('  الخليج   للتقنيه  '))->toBe($key)
        ->and(EmployerName::key('مجموعة الخليج للتقنية'))->toBe($key);

    // ولا مفتاحَ لما لا يُميّز: اسمٌ كلُّه صيغةٌ قانونية يُطابق كلَّ اسمٍ مثله.
    expect(EmployerName::key('شركة'))->toBeNull()
        ->and(EmployerName::key(''))->toBeNull()
        ->and(EmployerName::key(null))->toBeNull();
});
