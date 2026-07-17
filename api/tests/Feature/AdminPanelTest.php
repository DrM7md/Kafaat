<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Enums\VerificationStatus;
use App\Models\CandidateReveal;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Models\Skill;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use App\Support\Roles;
use App\Support\SettingKeys;
use Database\Seeders\LookupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    app(SettingsRepository::class)->flush();

    // ★ بوّابة الكفاءات **مطفأة افتراضاً** الآن: الموظّف ينشر فيظهر فوراً،
    //   والتحقّق مقصورٌ على الشركة — فهي من يرى ملفّات الناس.
    //
    //   هذا الملفّ مكرَّسٌ لدورة الاعتماد نفسها، فيُشعلها صراحةً. والآلة كلّها
    //   باقية: هكذا نضمن أنّ إشعال المفتاح من اللوحة يُعيدها كاملةً، بلا كود.
    app(SettingsRepository::class)->set(SettingKeys::MODERATION_REQUIRE_CANDIDATE_APPROVAL, true);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(Roles::SUPER_ADMIN);
});

// ═══ دورة الاعتماد ═══

it('★ الملفّ المُقدَّم لا يظهر في التصفّح قبل الاعتماد', function () {
    $company = CompanyProfile::factory()->create();

    EmployeeProfile::factory()->create();                      // معتمَد
    EmployeeProfile::factory()->awaitingApproval()->create();  // طلبٌ معلّق
    EmployeeProfile::factory()->rejected()->create();          // مرفوض

    $data = $this->actingAs($company->user)->getJson('/api/v1/candidates')->assertOk()->json('data');

    expect($data)->toHaveCount(1);
});

it('★ الملفّ المعلّق يُعاد بـ 404 حتى بمعرّفه المباشر', function () {
    $company = CompanyProfile::factory()->create();
    $profile = EmployeeProfile::factory()->awaitingApproval()->create();

    $this->actingAs($company->user)
        ->getJson("/api/v1/candidates/{$profile->ref}")
        ->assertNotFound();
});

it('★ الكشف عن ملفٍّ غير معتمَد مرفوض — لا خصم', function () {
    $company = CompanyProfile::factory()->create();
    $profile = EmployeeProfile::factory()->awaitingApproval()->create();

    $this->actingAs($company->user)
        ->withHeader('Idempotency-Key', 'k')
        ->postJson("/api/v1/candidates/{$profile->ref}/reveal")
        ->assertNotFound();

    $this->assertDatabaseCount('payments', 0);
});

it('نشر الموظّف ملفَّه يُنشئ طلباً معلّقاً لا يظهر فوراً', function () {
    // الاكتمال يُحسب من البيانات الفعلية وقت التقديم، لا من العمود المخزَّن.
    $this->seed(LookupSeeder::class);

    $profile = EmployeeProfile::factory()->unpublished()->create([
        'field_id' => Lookup::where('group', 'field')->value('id'),
        'city_id' => Lookup::where('group', 'city')->value('id'),
        'employment_status_id' => Lookup::where('group', 'employment_status')->value('id'),
        'years_experience' => 5,
        'summary' => 'خبرة عملية موثّقة.',
    ]);

    $profile->skills()->attach(Skill::create(['name' => 'PHP', 'slug' => 'php'])->id);

    $this->actingAs($profile->user)
        ->postJson('/api/v1/profile/publish')
        ->assertOk()
        ->assertJsonPath('approval_status', 'pending');

    $fresh = $profile->fresh();

    expect($fresh->approval_status)->toBe(ApprovalStatus::Pending)
        ->and($fresh->submitted_at)->not->toBeNull()
        // منشورٌ برغبة صاحبه، وغير ظاهرٍ لأنه لم يُعتمَد.
        ->and($fresh->published_at)->not->toBeNull()
        ->and($fresh->isPublished())->toBeFalse();
});

it('إعادة تقديم طلبٍ قيد المراجعة لا تُكرّره', function () {
    $profile = EmployeeProfile::factory()->awaitingApproval()->create();
    $submittedAt = $profile->submitted_at;

    $this->actingAs($profile->user)->postJson('/api/v1/profile/publish')->assertOk();

    expect($profile->fresh()->submitted_at->toIso8601String())->toBe($submittedAt->toIso8601String());
});

it('اعتماد المشرف يُظهر الملفّ ويكتب أثر تدقيق', function () {
    $profile = EmployeeProfile::factory()->awaitingApproval()->create();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/candidates/{$profile->ref}/approve")
        ->assertOk();

    $fresh = $profile->fresh();

    expect($fresh->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($fresh->approved_by)->toBe($this->admin->id)
        ->and($fresh->isPublished())->toBeTrue();

    $this->assertDatabaseHas('audit_logs', ['action' => 'profile.approved']);

    // والآن يراه المتصفّح.
    $company = CompanyProfile::factory()->create();
    expect($this->actingAs($company->user)->getJson('/api/v1/candidates')->json('data'))->toHaveCount(1);
});

it('الرفض يتطلّب سبباً ويُسجَّل', function () {
    $profile = EmployeeProfile::factory()->awaitingApproval()->create();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/candidates/{$profile->ref}/reject", [])
        ->assertStatus(422);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/admin/candidates/{$profile->ref}/reject", ['reason' => 'النبذة غير كافية.'])
        ->assertOk();

    expect($profile->fresh()->approval_status)->toBe(ApprovalStatus::Rejected)
        ->and($profile->fresh()->rejection_reason)->toBe('النبذة غير كافية.');

    $this->assertDatabaseHas('audit_logs', ['action' => 'profile.rejected']);
});

// ═══ قائمة الطلبات محجوبة ═══

it('★ قائمة طلبات الاعتماد لا تكشف هويّة أصحابها', function () {
    EmployeeProfile::factory()->awaitingApproval()->count(2)->create();

    $row = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/candidates?status=pending')
        ->assertOk()
        ->json('data.0');

    // من يعتمد يراجع جودة الملفّ، لا هويّة صاحبه.
    foreach (['name', 'email', 'phone', 'initials'] as $field) {
        expect($row)->not->toHaveKey($field, "الحقل «{$field}» تسرّب في قائمة الاعتماد");
    }

    expect($row)->toHaveKeys(['ref', 'title', 'completeness', 'approval_status', 'skills']);
});

it('يمنع من لا يملك profile.approve من رؤية الطلبات', function () {
    $company = CompanyProfile::factory()->create();

    $this->actingAs($company->user)->getJson('/api/v1/admin/candidates')->assertForbidden();
});

// ═══ الجدول: بحث · فرز · ترقيم ═══

it('البحث في الطلبات لا يمسّ اسم صاحب الملفّ', function () {
    $profile = EmployeeProfile::factory()->awaitingApproval()->create(['title' => 'مهندس بيانات']);
    $profile->user->update(['name' => 'سارة الهاجري']);

    // البحث بالاسم لا يُرجع شيئاً — الاستعلام قناة تسريب كالاستجابة.
    expect($this->actingAs($this->admin)->getJson('/api/v1/admin/candidates?q='.urlencode('سارة'))->json('data'))
        ->toBeEmpty();

    // والبحث بالمسمّى يُرجعه.
    expect($this->actingAs($this->admin)->getJson('/api/v1/admin/candidates?q='.urlencode('مهندس'))->json('data'))
        ->toHaveCount(1);
});

it('★ الفرز مقيَّد بقائمة أعمدة مسموحة', function () {
    EmployeeProfile::factory()->count(3)->create();

    // عمودٌ غير مسموح يسقط بصمتٍ إلى الافتراضي — لا خطأ SQL ولا تسريب.
    $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/candidates?sort=user_id&direction=asc')
        ->assertOk();

    $sorted = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/candidates?sort=completeness&direction=desc')
        ->assertOk()
        ->json('data');

    $values = array_column($sorted, 'completeness');
    expect($values)->toBe(array_reverse(collect($values)->sort()->values()->all()));
});

it('الترقيم يعمل ويُعيد البيانات الوصفية', function () {
    EmployeeProfile::factory()->count(12)->create();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/candidates?per_page=5&page=2')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(5)
        ->and($response->json('meta.total'))->toBe(12)
        ->and($response->json('meta.current_page'))->toBe(2)
        ->and($response->json('meta.last_page'))->toBe(3);
});

it('عدّادات الحالات تُرجع كل الحالات حتى الصفرية', function () {
    EmployeeProfile::factory()->awaitingApproval()->create();

    $counts = $this->actingAs($this->admin)->getJson('/api/v1/admin/candidates')->json('counts');

    expect($counts)->toHaveKeys(['draft', 'pending', 'approved', 'rejected'])
        ->and($counts['pending'])->toBe(1)
        ->and($counts['rejected'])->toBe(0);
});

// ═══ الشركات ═══

it('قائمة الشركات تعرض عدّاد الكشوفات وتدعم البحث', function () {
    $a = CompanyProfile::factory()->create(['legal_name' => 'شركة الخليج للتقنية']);
    CompanyProfile::factory()->create(['legal_name' => 'مصرف الدوحة']);

    CandidateReveal::factory()->completed()->create(['company_profile_id' => $a->id]);

    $data = $this->actingAs($this->admin)->getJson('/api/v1/admin/companies')->assertOk()->json('data');
    expect($data)->toHaveCount(2);

    $found = $this->actingAs($this->admin)
        ->getJson('/api/v1/admin/companies?q='.urlencode('الخليج'))
        ->json('data');

    expect($found)->toHaveCount(1)
        ->and($found[0]['reveals_count'])->toBe(1)
        ->and($found[0]['completed_reveals_count'])->toBe(1);
});

it('صفحة الشركة تعرض طلباتها بمبالغها المجمّدة', function () {
    $company = CompanyProfile::factory()->create();
    $profile = EmployeeProfile::factory()->create();

    CandidateReveal::factory()->completed()->create([
        'company_profile_id' => $company->id,
        'employee_profile_id' => $profile->id,
        'amount' => 75,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/admin/companies/{$company->id}")
        ->assertOk();

    expect($response->json('reveals'))->toHaveCount(1)
        ->and($response->json('reveals.0.amount'))->toBe(75)
        ->and($response->json('reveals.0.candidate.ref'))->toBe($profile->ref)
        ->and($response->json('stats.total_spent'))->toBe(75);
});

it('توثيق الشركة يُسجَّل في التدقيق', function () {
    $company = CompanyProfile::factory()->create();

    $this->actingAs($this->admin)
        ->putJson("/api/v1/admin/companies/{$company->id}/verification", ['status' => 'verified'])
        ->assertOk();

    expect($company->fresh()->verification_status)->toBe(VerificationStatus::Verified)
        ->and($company->fresh()->verified_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', ['action' => 'company.verification_updated']);
});

// ═══ الحذف الجماعي ═══

it('★ الحذف الجماعي ناعم — الصفوف المالية تبقى', function () {
    $company = CompanyProfile::factory()->create();
    $profile = EmployeeProfile::factory()->create();

    $reveal = CandidateReveal::factory()->completed()->create([
        'company_profile_id' => $company->id,
        'employee_profile_id' => $profile->id,
        'amount' => 75,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/companies/bulk', ['ids' => [$company->id]])
        ->assertOk()
        ->assertJsonPath('deleted', 1);

    expect(CompanyProfile::find($company->id))->toBeNull()
        ->and(CompanyProfile::withTrashed()->find($company->id))->not->toBeNull()
        // نزاهة الفواتير تسبق نظافة الجدول (ADR-012).
        ->and(CandidateReveal::find($reveal->id)?->amount)->toBe(75);

    $this->assertDatabaseHas('audit_logs', ['action' => 'company.deleted']);
});

it('الحذف الجماعي يرفض معرّفاً غير موجود', function () {
    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/admin/candidates/bulk', ['ids' => [999999]])
        ->assertStatus(422);
});

it('الحذف الجماعي محروس بصلاحية user.manage', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole(Roles::CONTENT_MODERATOR);

    $profile = EmployeeProfile::factory()->create();

    // يستطيع الاعتماد…
    $this->actingAs($moderator)
        ->postJson("/api/v1/admin/candidates/{$profile->ref}/approve")
        ->assertOk();

    // …ولا يستطيع الحذف.
    $this->actingAs($moderator)
        ->deleteJson('/api/v1/admin/candidates/bulk', ['ids' => [$profile->id]])
        ->assertForbidden();
});
