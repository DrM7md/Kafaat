<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use App\Support\Roles;
use App\Support\SettingKeys;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الاختبارات السبعة الملزمة — Brain/05-settings-engine.md §6.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    app(SettingsRepository::class)->flush();
});

it('١ — يُصبّب الأنواع: reveal.fee عددٌ صحيح لا نصّ', function () {
    expect(settings('reveal.fee'))->toBeInt()->toBe(75)
        ->and(settings('privacy.employer_blind'))->toBeBool()->toBeTrue()
        ->and(settings('masking.fields'))->toBeArray()
        ->and(settings('platform.name'))->toBeString();
});

it('٣ — كل كتابة تكتب أثر تدقيق بالقيمة قبل وبعد', function () {
    app(SettingsRepository::class)->set('reveal.fee', 120);

    $log = AuditLog::where('action', 'settings.updated')->firstOrFail();

    expect($log->meta['key'])->toBe('reveal.fee')
        ->and($log->meta['before'])->toBe(75)
        ->and($log->meta['after'])->toBe(120);
});

it('٤ — إعداد is_public=false لا يظهر في المسار العامّ', function () {
    $public = $this->getJson('/api/v1/settings/public')->assertOk()->json('settings');

    expect($public)->toHaveKey('reveal.fee')
        ->and($public)->not->toHaveKey('masking.fields')      // سرّ تشغيلي
        ->and($public)->not->toHaveKey('billing.gateway');
});

it('٥ — قيمة خارج options تُرفض', function () {
    expect(fn () => app(SettingsRepository::class)->set('billing.model', 'bitcoin'))
        ->toThrow(ValidationException::class);
});

it('٦ — إزالة الاسم من masking.fields تُرفض دون تأكيد', function () {
    expect(fn () => app(SettingsRepository::class)->set('masking.fields', ['phone']))
        ->toThrow(ValidationException::class);
});

it('٧ — الكاش يُبطَل عند الكتابة: استعلامٌ واحد بعد التعديل', function () {
    app(SettingsRepository::class)->all();   // تسخين الكاش

    DB::enableQueryLog();
    settings('reveal.fee');
    settings('platform.name');
    settings('masking.fields');

    // ثلاث قراءات، صفر استعلام: الكاش يعمل.
    expect(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();
});

it('لوحة الإعدادات محروسة بالصلاحية', function () {
    $company = User::factory()->create();
    $company->assignRole(Roles::COMPANY);

    $this->actingAs($company)->getJson('/api/v1/admin/settings')->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole(Roles::SUPER_ADMIN);

    $this->actingAs($admin)->getJson('/api/v1/admin/settings')->assertOk();
});

it('تعديل الإعداد من الـ API ينعكس على الطلب التالي بلا نشر كود', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Roles::SUPER_ADMIN);

    // معيار قبول م7: رفع الرسم من ٧٥ إلى ١٠٠ من اللوحة.
    $this->actingAs($admin)
        ->putJson('/api/v1/admin/settings', ['key' => 'reveal.fee', 'value' => 100])
        ->assertOk()
        ->assertJsonPath('value', 100);

    expect(settings('reveal.fee'))->toBe(100);
});

it('كل مفتاح في الكتالوج مبذور فعلياً', function () {
    $reflection = new ReflectionClass(SettingKeys::class);

    foreach ($reflection->getConstants() as $name => $key) {
        if (! is_string($key)) {
            continue;
        }

        expect(Setting::query()->whereKey($key)->exists())
            ->toBeTrue("المفتاح {$name} ({$key}) غير مبذور");
    }
});
