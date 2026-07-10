<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\RevealStatus;
use App\Models\AuditLog;
use App\Models\CandidateReveal;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\Payment;
use App\Notifications\ProfileRevealedNotification;
use App\Services\Payments\ManualGateway;
use App\Services\Reveal\RefundNotAllowedException;
use App\Services\Reveal\RevealService;
use App\Services\Settings\SettingsRepository;
use App\Support\SettingKeys;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * آلة حالات الدفع والكشف — الضمانات الأربعة (Brain/06 §6).
 *
 * ★ كل اختبارٍ هنا يحرس علّةً وقعت فعلاً، لا علّةً متخيَّلة.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    app(SettingsRepository::class)->flush();

    $this->company = CompanyProfile::factory()->create();
    $this->profile = EmployeeProfile::factory()->create();
});

/** @return array{0: string, 1: string} */
function signedEvent(string $providerRef, int $amount, string $type = 'payment.succeeded'): array
{
    $body = json_encode(
        ['type' => $type, 'provider_ref' => $providerRef, 'amount' => $amount],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );

    return [$body, hash_hmac('sha256', $body, app(ManualGateway::class)->secret())];
}

function postEvent(string $body, string $signature, string $provider = 'manual'): TestResponse
{
    return test()->call('POST', "/api/v1/payments/webhook/{$provider}", [], [], [],
        ['HTTP_X_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $body);
}

function startReveal(string $key = 'key-1'): TestResponse
{
    return test()->actingAs(test()->company->user)
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/candidates/'.test()->profile->ref.'/reveal');
}

// ─────────────────────────────────────────────────────────────
// فشل الدفع يجب أن يُحرّر الخانة
// ─────────────────────────────────────────────────────────────

it('★ فشل الدفع يُفشِل الكشف معه — لا يتركه معلّقاً', function () {
    startReveal()->assertStatus(202);

    $payment = Payment::firstOrFail();
    [$body, $sig] = signedEvent($payment->provider_ref, 75, 'payment.failed');

    postEvent($body, $sig)->assertOk();

    expect(Payment::firstOrFail()->status)->toBe(PaymentStatus::Failed)
        ->and(CandidateReveal::firstOrFail()->status)->toBe(RevealStatus::Failed);
});

it('★ بعد فشل الدفع تستطيع الشركة إعادة المحاولة على المرشّح نفسه', function () {
    // ★ العلّة: الكشف يبقى `pending`، والعمود المولَّد يعدّه خانةً مشغولة،
    //   فيرفض الفهرس الفريد كل محاولةٍ لاحقة ⇒ 409 إلى الأبد.
    startReveal('key-1')->assertStatus(202);

    $payment = Payment::firstOrFail();
    [$body, $sig] = signedEvent($payment->provider_ref, 75, 'payment.failed');
    postEvent($body, $sig)->assertOk();

    startReveal('key-2')->assertStatus(202);

    expect(Payment::count())->toBe(2)
        ->and(CandidateReveal::count())->toBe(2)
        ->and(CandidateReveal::where('status', RevealStatus::Pending)->count())->toBe(1);
});

it('★ المهمّة المجدولة تُفشِل الكشوف المعلّقة المهجورة فتُحرّر الخانة', function () {
    startReveal('key-1')->assertStatus(202);

    // دفعةٌ هُجرت: لا webhook يأتي أبداً.
    CandidateReveal::query()->update(['created_at' => now()->subHours(3)]);

    $this->artisan('kafaat:expire-stale-reveals', ['--minutes' => 60])->assertSuccessful();

    expect(CandidateReveal::firstOrFail()->status)->toBe(RevealStatus::Failed)
        ->and(Payment::firstOrFail()->status)->toBe(PaymentStatus::Failed);

    startReveal('key-2')->assertStatus(202);
});

it('المهمّة المجدولة لا تمسّ كشفاً معلّقاً حديثاً', function () {
    startReveal('key-1')->assertStatus(202);

    $this->artisan('kafaat:expire-stale-reveals', ['--minutes' => 60])->assertSuccessful();

    expect(CandidateReveal::firstOrFail()->status)->toBe(RevealStatus::Pending);
});

// ─────────────────────────────────────────────────────────────
// حارس الحالة النهائية في الـ webhook
// ─────────────────────────────────────────────────────────────

it('★ حدث فشلٍ مُعاد بعد النجاح لا يُخفّض دفعةً مدفوعة', function () {
    Notification::fake();

    startReveal()->assertStatus(202);
    $payment = Payment::firstOrFail();

    [$okBody, $okSig] = signedEvent($payment->provider_ref, 75);
    postEvent($okBody, $okSig)->assertOk();

    // البوّابات تُعيد الأحداث وتخلط ترتيبها.
    [$failBody, $failSig] = signedEvent($payment->provider_ref, 75, 'payment.failed');
    postEvent($failBody, $failSig)->assertOk();

    expect(Payment::firstOrFail()->status)->toBe(PaymentStatus::Paid)
        ->and(CandidateReveal::firstOrFail()->status)->toBe(RevealStatus::Completed);
});

it('★ فشلٌ ثمّ نجاحٌ مُعادان لا يُكرّران الإشعار ولا أثر التدقيق', function () {
    // ★ العلّة: حدث الفشل كان يُرجع الحالة إلى `failed` بلا فحص، فيجتاز
    //   حدثُ النجاح المُعاد حارسَ التكرار ⇒ إشعارٌ ثانٍ للموظّف.
    Notification::fake();

    startReveal()->assertStatus(202);
    $payment = Payment::firstOrFail();

    [$okBody, $okSig] = signedEvent($payment->provider_ref, 75);
    [$failBody, $failSig] = signedEvent($payment->provider_ref, 75, 'payment.failed');

    postEvent($okBody, $okSig)->assertOk();
    postEvent($failBody, $failSig)->assertOk();
    postEvent($okBody, $okSig)->assertOk();

    Notification::assertSentToTimes($this->profile->user, ProfileRevealedNotification::class, 1);
    expect(AuditLog::where('action', 'candidate.revealed')->count())->toBe(1);
});

it('يرفض webhook من بوّابةٍ غير مفعّلة', function () {
    startReveal()->assertStatus(202);
    $payment = Payment::firstOrFail();

    [$body, $sig] = signedEvent($payment->provider_ref, 75);

    // التوقيع صحيح، لكن المسار يدّعي بوّابةً أخرى: لا نتحقّق بسرّ بوّابتنا.
    postEvent($body, $sig, 'stripe')->assertNotFound();

    expect(CandidateReveal::firstOrFail()->status)->toBe(RevealStatus::Pending);
});

// ─────────────────────────────────────────────────────────────
// الاسترداد
// ─────────────────────────────────────────────────────────────

it('★ الاسترداد مرّتين يُرفَض — لا مال يخرج مرّتين', function () {
    Notification::fake();

    startReveal()->assertStatus(202);
    $payment = Payment::firstOrFail();
    [$body, $sig] = signedEvent($payment->provider_ref, 75);
    postEvent($body, $sig)->assertOk();

    $reveal = CandidateReveal::firstOrFail();
    $service = app(RevealService::class);

    $service->refund($reveal);

    expect(fn () => $service->refund($reveal->fresh()))
        ->toThrow(RefundNotAllowedException::class);

    expect(AuditLog::where('action', 'payment.refunded')->count())->toBe(1)
        ->and(Payment::firstOrFail()->status)->toBe(PaymentStatus::Refunded);
});

it('لا يُستردّ كشفٌ معلّق لم يُدفَع بعد', function () {
    startReveal()->assertStatus(202);

    expect(fn () => app(RevealService::class)->refund(CandidateReveal::firstOrFail()))
        ->toThrow(RefundNotAllowedException::class);

    expect(Payment::firstOrFail()->status)->toBe(PaymentStatus::Pending);
});

// ─────────────────────────────────────────────────────────────
// الدفع قيد التطوير
// ─────────────────────────────────────────────────────────────

it('★ بوّابةٌ غير منفَّذة لا تسقط صامتاً إلى بوّابة التطوير', function () {
    // ★ العلّة: `match { default => new ManualGateway }` كان يجعل اختيار
    //   «stripe» من اللوحة دفعاً وهمياً يظنّه المشرف حقيقياً.
    app(SettingsRepository::class)->set(SettingKeys::BILLING_GATEWAY, 'stripe');

    startReveal()->assertStatus(503);

    expect(Payment::count())->toBe(0)
        ->and(CandidateReveal::count())->toBe(0);
});

it('يُعلن للواجهة أن الدفع قيد التطوير', function () {
    $public = $this->getJson('/api/v1/settings/public')->assertOk()->json('settings');

    expect($public)->toHaveKey(SettingKeys::BILLING_UNDER_DEVELOPMENT)
        ->and($public[SettingKeys::BILLING_UNDER_DEVELOPMENT])->toBeTrue();
});
