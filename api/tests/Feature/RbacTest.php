<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use App\Support\Permissions;
use App\Support\Roles;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * الاختبارات التسعة الملزمة — Brain/04-rbac.md §8.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    app(SettingsRepository::class)->flush();
});

it('١ — مستخدم بلا صلاحية الكشف يُمنع', function () {
    $profile = EmployeeProfile::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('Idempotency-Key', 'k')
        ->postJson("/api/v1/candidates/{$profile->ref}/reveal")
        ->assertForbidden();
});

it('٢ — الصلاحية عبر دور تكفي', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::COMPANY);

    expect($user->can(Permissions::CANDIDATE_BROWSE))->toBeTrue();
});

it('٣ — صلاحية مباشرة بلا دور تكفي', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permissions::CANDIDATE_BROWSE);

    expect($user->can(Permissions::CANDIDATE_BROWSE))->toBeTrue()
        ->and($user->roles)->toBeEmpty();
});

it('٤ — منعٌ فرديّ يتجاوز الدور', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::COMPANY);

    expect($user->can(Permissions::CANDIDATE_REVEAL))->toBeTrue();

    // نزع الصلاحية من الدور لهذا المستخدم عبر دورٍ بديل بلا الصلاحية.
    $limited = Role::findOrCreate('company-limited', 'web');
    $limited->syncPermissions([Permissions::CANDIDATE_BROWSE]);

    $user->syncRoles([$limited]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($user->fresh()->can(Permissions::CANDIDATE_REVEAL))->toBeFalse()
        ->and($user->fresh()->can(Permissions::CANDIDATE_BROWSE))->toBeTrue();
});

it('٥ — المشرف العام ينجح في كل شيء عبر Gate::before', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Roles::SUPER_ADMIN);

    // super-admin لا يملك أي صلاحية مُسندة صراحةً…
    expect($admin->getAllPermissions())->toBeEmpty();

    // …ومع ذلك يجوز له كل شيء.
    foreach (Permissions::all() as $permission) {
        expect($admin->can($permission))->toBeTrue("المشرف مُنع من {$permission}");
    }
});

it('★ /me تُرسل للمشرف العام كتالوج الصلاحيات كاملاً', function () {
    // كانت effectivePermissions() تقرأ getAllPermissions() — وهي فارغة
    // للمشرف العام. فأخفت الواجهةُ اللوحةَ كلّها عنه. الآن تُحسب عبر الـ Gate،
    // فتحترم Gate::before.
    $admin = User::factory()->create();
    $admin->assignRole(Roles::SUPER_ADMIN);

    $permissions = $this->actingAs($admin)->getJson('/api/v1/me')->assertOk()->json('permissions');

    expect($permissions)->toHaveCount(count(Permissions::all()))
        ->toContain('settings.update', 'rbac.manage', 'audit.view', 'user.manage');
});

it('★ /me لا تُسرّب صلاحياتٍ لا يملكها المستخدم', function () {
    $company = User::factory()->create();
    $company->assignRole(Roles::COMPANY);

    $permissions = $this->actingAs($company)->getJson('/api/v1/me')->assertOk()->json('permissions');

    expect($permissions)->toContain('candidate.browse', 'candidate.reveal')
        ->not->toContain('settings.update')
        ->not->toContain('rbac.manage');
});

it('٦ — نزع صلاحية من دور يُفقدها المستخدمين فوراً', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::COMPANY);

    expect($user->can(Permissions::CANDIDATE_REVEAL))->toBeTrue();

    Role::findByName(Roles::COMPANY)->revokePermissionTo(Permissions::CANDIDATE_REVEAL);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($user->fresh()->can(Permissions::CANDIDATE_REVEAL))->toBeFalse();
});

it('٧ — إعادة تشغيل PermissionSeeder لا تُكرّر ولا تفقد إسنادات', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::COMPANY);

    $before = Permission::count();

    $this->seed(PermissionSeeder::class);
    $this->seed(PermissionSeeder::class);

    expect(Permission::count())->toBe($before)
        ->and($user->fresh()->hasRole(Roles::COMPANY))->toBeTrue()
        ->and($user->fresh()->can(Permissions::CANDIDATE_REVEAL))->toBeTrue();
});

it('٨ — لا يستطيع المستخدم نزع rbac.manage عن نفسه', function () {
    $role = Role::findOrCreate('rbac-admin', 'web');
    $role->syncPermissions([Permissions::RBAC_MANAGE]);

    $user = User::factory()->create();
    $user->assignRole($role);

    // قفل الباب من الداخل: لولا الحارس لبقي النظام بلا من يديره.
    $this->actingAs($user)
        ->putJson("/api/v1/admin/roles/{$role->id}/permissions", ['permissions' => []])
        ->assertStatus(422);
});

it('٩ — مصفوفة الصلاحيات تُعيد الكتالوج والحرجة', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Roles::SUPER_ADMIN);

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/roles')->assertOk();

    expect($response->json('catalog'))->toHaveKeys(['الملفّات', 'الكشف', 'الإعدادات'])
        ->and($response->json('critical'))->toContain('profile.moderate', 'rbac.manage');
});

it('حفظ الصلاحيات تفاضليّ ويُسجَّل ما تغيّر بدقّة', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Roles::SUPER_ADMIN);

    $role = Role::findByName(Roles::SUPPORT);

    $this->actingAs($admin)
        ->putJson("/api/v1/admin/roles/{$role->id}/permissions", [
            'permissions' => [Permissions::PROFILE_VIEW, Permissions::PAYMENT_REFUND],
        ])->assertOk();

    $log = AuditLog::where('action', 'role.permissions_updated')->firstOrFail();

    expect($log->meta['added'])->toContain('payment.refund')
        ->and($log->meta['removed'])->toContain('audit.view', 'company.view');
});

it('لا يُحذف دورٌ بنيويّ يعتمد عليه التسجيل', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Roles::SUPER_ADMIN);

    $role = Role::findByName(Roles::EMPLOYEE);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/roles/{$role->id}")
        ->assertStatus(422);
});
