<?php

declare(strict_types=1);

use App\Enums\ConsentType;
use App\Models\AuditLog;
use App\Models\CandidateReveal;
use App\Models\CompanyProfile;
use App\Models\Consent;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\Testing\File;

/**
 * قانون حماية البيانات القطري رقم 13 — Brain/09-legal-compliance.md.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    app(SettingsRepository::class)->flush();
});

it('التسجيل يوثّق الموافقة بنسختها ووقتها وعنوان الشبكة', function () {
    $this->postJson('/api/v1/auth/register/employee', [
        'name' => 'أحمد الكواري',
        'email' => 'a@kafaat.qa',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'title' => 'مهندس برمجيات',
        'accept_terms' => true,
        'accept_privacy' => true,
    ])->assertCreated();

    $user = User::firstWhere('email', 'a@kafaat.qa');

    expect(Consent::where('user_id', $user->id)->count())->toBe(2);

    $terms = Consent::where('user_id', $user->id)->where('type', ConsentType::Terms)->firstOrFail();

    expect($terms->version)->toBe('1.0')
        ->and($terms->granted_at)->not->toBeNull()
        ->and($terms->ip)->not->toBeNull();
});

it('لا تسجيل دون موافقة صريحة', function () {
    $this->postJson('/api/v1/auth/register/employee', [
        'name' => 'x', 'email' => 'x@kafaat.qa',
        'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        'title' => 'y',
        'accept_terms' => false,
        'accept_privacy' => true,
    ])->assertStatus(422)->assertJsonValidationErrors('accept_terms');
});

it('حقّ الوصول: التصدير يشمل بيانات المستخدم ويُسجَّل', function () {
    $profile = EmployeeProfile::factory()->create();

    $this->actingAs($profile->user)
        ->getJson('/api/v1/me/data-export')
        ->assertOk()
        ->assertJsonStructure(['exported_at', 'account', 'roles', 'consents', 'employee_profile']);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $profile->user->id,
        'action' => 'user.data_exported',
    ]);
});

it('★ ADR-012: الحذف إخفاء هوية — تبقى السجلّات المالية بلا معرّفات', function () {
    $company = CompanyProfile::factory()->create();
    $profile = EmployeeProfile::factory()->create();
    $userId = $profile->user->id;

    $reveal = CandidateReveal::factory()->completed()->create([
        'company_profile_id' => $company->id,
        'employee_profile_id' => $profile->id,
        'amount' => 75,
    ]);

    $this->actingAs($profile->user)
        ->deleteJson('/api/v1/me', ['password' => 'password', 'confirm' => true])
        ->assertOk();

    $user = User::withTrashed()->findOrFail($userId);

    // الحقول المعرِّفة صُفّرت.
    expect($user->name)->toBe('مستخدم محذوف')
        ->and($user->email)->toContain('@kafaat.invalid')
        ->and($user->phone)->toBeNull()
        ->and($user->trashed())->toBeTrue();

    // الصفّ المالي باقٍ: نزاهة الفواتير.
    expect(CandidateReveal::find($reveal->id))->not->toBeNull()
        ->and(CandidateReveal::find($reveal->id)->amount)->toBe(75);

    // وأثر التدقيق باقٍ: المساءلة تتطلّبه.
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.anonymized']);
});

it('الحذف يتطلّب كلمة المرور وتأكيداً', function () {
    $profile = EmployeeProfile::factory()->create();

    $this->actingAs($profile->user)
        ->deleteJson('/api/v1/me', ['password' => 'wrong', 'confirm' => true])
        ->assertStatus(422);
});

it('★ ADR-011: سجلّ التدقيق إلحاقيّ — لا يُعدَّل ولا يُحذَف', function () {
    $log = AuditLog::create(['action' => 'test.action']);

    expect(fn () => $log->update(['action' => 'tampered']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);

    expect(AuditLog::findOrFail($log->id)->action)->toBe('test.action');
});

it('★ ADR-013: رفع شهادة حُسن السيرة معطّل افتراضياً', function () {
    expect(settings('features.good_conduct_upload'))->toBeFalse();

    $profile = EmployeeProfile::factory()->create();

    $this->actingAs($profile->user)
        ->postJson('/api/v1/profile/attachments', [
            'type' => 'good_conduct',
            'file' => File::create('cert.pdf', 100),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('type');
});
