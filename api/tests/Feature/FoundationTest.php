<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('يبني جداول الصلاحيات من Spatie', function () {
    expect(Schema::hasTable('roles'))->toBeTrue()
        ->and(Schema::hasTable('permissions'))->toBeTrue()
        ->and(Schema::hasTable('model_has_roles'))->toBeTrue()
        ->and(Schema::hasTable('role_has_permissions'))->toBeTrue()
        // صلاحيات مباشرة على المستخدم — أساس «الاستثناءات الفردية» (Brain/04 §2)
        ->and(Schema::hasTable('model_has_permissions'))->toBeTrue();
});

it('يبني جدول توكنات Sanctum لمسار الجوال المستقبلي', function () {
    expect(Schema::hasTable('personal_access_tokens'))->toBeTrue();
});

it('يختبر على MySQL لا SQLite', function () {
    // lockForUpdate() لا أثر له في SQLite. اختبار منع الخصم المزدوج (م5)
    // يعتمد عليه، فلو انزلقت الاختبارات إلى SQLite لمرّت كذباً.
    expect(config('database.default'))->toBe('mysql');
})->group('guard');

// ── ADR-002: كوكيز httpOnly لا توكن في localStorage ──

it('يُفعّل الاعتماد على الكوكيز في CORS', function () {
    expect(config('cors.supports_credentials'))->toBeTrue();
})->group('guard');

it('لا يسمح بأصلٍ شامل (*) مع الكوكيز', function () {
    // الجمع بين Access-Control-Allow-Origin: * والكوكيز يرفضه المتصفّح،
    // وهو أيضاً خطأ أمني. الأصل يُسرَد صراحةً.
    expect(config('cors.allowed_origins'))->not->toContain('*');
})->group('guard');

it('يشمل مسار csrf-cookie في CORS', function () {
    expect(config('cors.paths'))->toContain('sanctum/csrf-cookie');
})->group('guard');

it('يعرّف نطاقات Sanctum الحالِّية', function () {
    expect(config('sanctum.stateful'))->toContain('localhost:5173');
})->group('guard');
