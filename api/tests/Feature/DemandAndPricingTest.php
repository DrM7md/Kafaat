<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\CandidateReveal;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Models\Payment;
use App\Models\User;
use App\Services\Reveal\RevealPricing;
use App\Services\Settings\SettingsRepository;
use App\Support\Permissions;
use App\Support\SettingKeys;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;

/**
 * مؤشّر الطلب، ومدّة الإدراج، والتسعير لكل مجال.
 *
 * ★ الخطر الأوّل: تسريب **عدد** الكشوف. «كشفته 5 شركات» يُخبر الشركةَ بنشاط
 *   منافسيها على مرشّحٍ بعينه، ويصِم من لم يُطلَب بعدُ بـ «0».
 *
 * ★ الخطر الثاني: كسر تجميد السعر (ADR-006). التسعير لكل مجال يجب ألّا
 *   يُعيد تسعير فاتورةٍ قائمة.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    $this->seed(LookupSeeder::class);
    app(SettingsRepository::class)->flush();
});

function pricingAdmin(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permissions::SETTINGS_VIEW);
    $user->givePermissionTo(Permissions::SETTINGS_UPDATE);

    return $user;
}

function browsingCompany(): CompanyProfile
{
    return CompanyProfile::factory()->create();
}

function itField(): Lookup
{
    return Lookup::query()->where('group', Lookup::FIELD)->where('key', 'it')->firstOrFail();
}

/** يُنشئ كشوفاً مكتملة من شركاتٍ مختلفة على المرشّح نفسه. */
function revealsOn(EmployeeProfile $profile, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        CandidateReveal::factory()->completed()->create([
            'employee_profile_id' => $profile->id,
            'company_profile_id' => CompanyProfile::factory()->create()->id,
        ]);
    }
}

it('★ 1 — عدد الكشوف لا يغادر الخادم أبداً — الشارة وحدها', function () {
    $profile = EmployeeProfile::factory()->create();
    revealsOn($profile, 9);

    $card = $this->actingAs(browsingCompany()->user)
        ->getJson('/api/v1/candidates')
        ->assertOk()
        ->json('data.0');

    // لو تسرّب العدد لعرف كلُّ صاحب عملٍ كم منافساً يلاحق هذا المرشّح.
    expect($card)->not->toHaveKey('reveals_count')
        ->and($card)->not->toHaveKey('reveals')
        ->and($card['demand'])->toBe('high');
});

it('2 — دون العتبة لا شارة: من لم يُطلَب بعدُ لا يُوصَم بـ «0»', function () {
    $quiet = EmployeeProfile::factory()->create();
    revealsOn($quiet, 2);   // العتبة 3

    $card = $this->actingAs(browsingCompany()->user)
        ->getJson('/api/v1/candidates')
        ->assertOk()
        ->json('data.0');

    expect($card['demand'])->toBeNull();
});

it('3 — العتبتان تُضبطان من اللوحة بلا نشر كود', function () {
    $profile = EmployeeProfile::factory()->create();
    revealsOn($profile, 2);

    $company = browsingCompany();

    expect($this->actingAs($company->user)->getJson('/api/v1/candidates')->json('data.0.demand'))->toBeNull();

    app(SettingsRepository::class)->set(SettingKeys::DEMAND_BADGE_MIN, 2);

    expect($this->actingAs($company->user)->getJson('/api/v1/candidates')->json('data.0.demand'))->toBe('rising');
});

it('4 — الكشف المعلَّق لا يُحسَب طلباً: الطلب مالٌ دُفع لا نيّةٌ تُركت', function () {
    $profile = EmployeeProfile::factory()->create();

    // خمسة كشوف معلَّقة — لم يُدفع فيها شيء. كلٌّ من شركةٍ مختلفة: الفهرس
    // الفريد (ADR-010) يمنع كشفين نشطين للزوج الواحد.
    for ($i = 0; $i < 5; $i++) {
        CandidateReveal::factory()->create([
            'employee_profile_id' => $profile->id,
            'company_profile_id' => CompanyProfile::factory()->create()->id,
        ]);
    }

    $card = $this->actingAs(browsingCompany()->user)
        ->getJson('/api/v1/candidates')
        ->assertOk()
        ->json('data.0');

    expect($card['demand'])->toBeNull();
});

it('5 — «مُدرَج منذ» يُحسب من الاعتماد لا من إنشاء الحساب', function () {
    // حسابٌ قديم، وملفٌّ اعتُمد قبل يومين — ليس «قديماً في السوق».
    $profile = EmployeeProfile::factory()->create([
        'approved_at' => now()->subDays(2),
        'published_at' => now()->subDays(2),
    ]);
    $profile->user->forceFill(['created_at' => now()->subDays(300)])->save();

    $card = $this->actingAs(browsingCompany()->user)
        ->getJson('/api/v1/candidates')
        ->assertOk()
        ->json('data.0');

    expect($card['listed_since_days'])->toBe(2);
});

it('6 — الترتيب الافتراضي: الأحدث إدراجاً أوّلاً', function () {
    $old = EmployeeProfile::factory()->create(['approved_at' => now()->subDays(30)]);
    $fresh = EmployeeProfile::factory()->create(['approved_at' => now()->subDay()]);

    $refs = array_column(
        $this->actingAs(browsingCompany()->user)->getJson('/api/v1/candidates')->json('data'),
        'ref',
    );

    expect($refs[0])->toBe($fresh->ref)
        ->and($refs[1])->toBe($old->ref);
});

it('★ 7 — سعر المجال هو ما يُعرَض وما يُخصَم — لا يتباعدان', function () {
    $it = itField();
    $it->forceFill(['meta' => [RevealPricing::META_KEY => 250]])->save();

    $profile = EmployeeProfile::factory()->create(['field_id' => $it->id]);
    $company = browsingCompany();

    // ما تعرضه البطاقة.
    $shown = $this->actingAs($company->user)
        ->getJson('/api/v1/candidates')
        ->json('data.0.reveal_fee');

    expect($shown)->toBe(250);

    // ما يخصمه الخادم فعلاً.
    $this->actingAs($company->user)
        ->withHeader('Idempotency-Key', 'key-'.uniqid())
        ->postJson("/api/v1/candidates/{$profile->ref}/reveal")
        ->assertStatus(202);

    expect(Payment::query()->firstOrFail()->amount)->toBe(250);
});

it('8 — مجالٌ بلا سعرٍ خاصّ يرث الافتراضي', function () {
    $profile = EmployeeProfile::factory()->create(['field_id' => itField()->id]);

    $fee = $this->actingAs(browsingCompany()->user)
        ->getJson('/api/v1/candidates')
        ->json('data.0.reveal_fee');

    expect($fee)->toBe(75);
});

it('9 — صفر سعرٌ مشروع، لا «بلا سعر»', function () {
    $zeroFee = itField();
    $zeroFee->forceFill(['meta' => [RevealPricing::META_KEY => 0]])->save();

    EmployeeProfile::factory()->create(['field_id' => $zeroFee->id]);

    $fee = $this->actingAs(browsingCompany()->user)
        ->getJson('/api/v1/candidates')
        ->json('data.0.reveal_fee');

    // لو عوملت الصفر كـ «غير مسعَّر» لورث 75 — ولاستحال كشفٌ مجّاني.
    expect($fee)->toBe(0);
});

it('★ 10 — ADR-006 صامد: رفع سعر المجال لا يمسّ فاتورةً قائمة', function () {
    $it = itField();
    $profile = EmployeeProfile::factory()->create(['field_id' => $it->id]);
    $company = browsingCompany();

    $this->actingAs($company->user)
        ->withHeader('Idempotency-Key', 'key-'.uniqid())
        ->postJson("/api/v1/candidates/{$profile->ref}/reveal")
        ->assertStatus(202);

    $payment = Payment::query()->firstOrFail();
    expect($payment->amount)->toBe(75);

    // المشرف يرفع سعر المجال إلى 300.
    $this->actingAs(pricingAdmin())
        ->putJson("/api/v1/admin/reveal-pricing/{$it->id}", ['fee' => 300])
        ->assertOk();

    // الفاتورة السابقة مجمّدة.
    expect($payment->fresh()->amount)->toBe(75);
});

it('11 — لوحة التسعير تعرض كل المجالات وعدد مرشّحيها', function () {
    EmployeeProfile::factory()->count(3)->create(['field_id' => itField()->id]);

    $data = $this->actingAs(pricingAdmin())
        ->getJson('/api/v1/admin/reveal-pricing')
        ->assertOk()
        ->json();

    expect($data['default_fee'])->toBe(75)
        ->and($data['fields'])->toHaveCount(10);

    $it = collect($data['fields'])->firstWhere('label', 'تقنية المعلومات');

    expect($it['fee'])->toBeNull()          // لا سعرَ خاصّاً بعد
        ->and($it['effective_fee'])->toBe(75)   // فيرث الافتراضي
        ->and($it['candidates'])->toBe(3);
});

it('12 — التسعير يكتب أثر تدقيق بالقيمة قبل وبعد', function () {
    $it = itField();

    $this->actingAs(pricingAdmin())
        ->putJson("/api/v1/admin/reveal-pricing/{$it->id}", ['fee' => 200])
        ->assertOk();

    $log = AuditLog::query()->where('action', 'reveal.fee_updated')->firstOrFail();

    expect($log->meta['before'])->toBeNull()
        ->and($log->meta['after'])->toBe(200);
});

it('13 — إعادة المجال للافتراضي تمحو سعره الخاصّ ولا تطمس ألوانه', function () {
    $status = Lookup::query()->where('group', Lookup::EMPLOYMENT_STATUS)->firstOrFail();
    $it = itField();

    $this->actingAs(pricingAdmin())->putJson("/api/v1/admin/reveal-pricing/{$it->id}", ['fee' => 200])->assertOk();
    $this->actingAs(pricingAdmin())->putJson("/api/v1/admin/reveal-pricing/{$it->id}", ['fee' => null])->assertOk();

    expect($it->fresh()->meta)->toBeNull();

    // `meta` تحمل ألوان الشارة لغير المجالات — لم نمسّها.
    expect($status->fresh()->meta)->toHaveKey('bg');
});

it('14 — من لا يملك settings.update لا يُسعّر', function () {
    $this->actingAs(EmployeeProfile::factory()->create()->user)
        ->putJson('/api/v1/admin/reveal-pricing/'.itField()->id, ['fee' => 1])
        ->assertForbidden();
});
