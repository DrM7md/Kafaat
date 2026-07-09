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
use App\Services\Reveal\RevealService;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * معايير قبول م5 — Brain/08-phases.md.
 *
 * ★ أخطر شيفرة في المشروع: مالٌ وتزامن معاً.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SettingSeeder::class);
    app(SettingsRepository::class)->flush();

    $this->company = CompanyProfile::factory()->create();
    $this->profile = EmployeeProfile::factory()->create();
});

function webhook(string $providerRef, int $amount, string $type = 'payment.succeeded'): array
{
    return ['type' => $type, 'provider_ref' => $providerRef, 'amount' => $amount];
}

function signedWebhook(array $payload): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return [$body, hash_hmac('sha256', $body, app(ManualGateway::class)->secret())];
}

it('يرفض الكشف بلا ترويسة Idempotency-Key', function () {
    $this->actingAs($this->company->user)
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal")
        ->assertStatus(422);
});

it('ينشئ دفعةً معلّقة ولا يفكّ الحجب قبل الدفع', function () {
    $response = $this->actingAs($this->company->user)
        ->withHeader('Idempotency-Key', 'key-1')
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal");

    $response->assertStatus(202)->assertJsonStructure(['payment_url', 'reveal_id']);

    // `pending` لا تفكّ الحجب. والاستجابة لا تحوي الملفّ أصلاً.
    expect($response->json())->not->toHaveKey('name');

    $this->assertDatabaseHas('candidate_reveals', ['status' => RevealStatus::Pending->value]);
    $this->assertDatabaseHas('payments', ['status' => PaymentStatus::Pending->value, 'amount' => 75]);

    // الملفّ ما زال محجوباً في مسار العرض.
    $data = $this->actingAs($this->company->user)
        ->getJson("/api/v1/candidates/{$this->profile->ref}")->json('data');

    expect($data)->not->toHaveKey('name');
});

it('★ لا خصم مزدوج: طلبان بمفتاحين مختلفين ⇒ 409 ودفعةٌ واحدة', function () {
    $this->actingAs($this->company->user)
        ->withHeader('Idempotency-Key', 'key-A')
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal")
        ->assertStatus(202);

    // الفهرس الفريد على العمود المولَّد يحرس الخانة.
    $this->actingAs($this->company->user)
        ->withHeader('Idempotency-Key', 'key-B')
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal")
        ->assertStatus(409);

    expect(Payment::count())->toBe(1)
        ->and(CandidateReveal::count())->toBe(1);
});

it('★ إعادة إرسال الطلب نفسه (نفس المفتاح) لا تُنشئ سجلّاً ثانياً', function () {
    foreach (range(1, 3) as $_) {
        $this->actingAs($this->company->user)
            ->withHeader('Idempotency-Key', 'same-key')
            ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal")
            ->assertStatus(202);
    }

    expect(Payment::count())->toBe(1)->and(CandidateReveal::count())->toBe(1);
});

it('★ webhook موقّع يُتمّ الكشف ويفكّ الحجب', function () {
    Notification::fake();

    $this->actingAs($this->company->user)
        ->withHeader('Idempotency-Key', 'key-1')
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal");

    $payment = Payment::firstOrFail();

    [$body, $signature] = signedWebhook(webhook($payment->provider_ref, 75));

    $this->call('POST', '/api/v1/payments/webhook/manual', [], [], [],
        ['HTTP_X_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and(CandidateReveal::firstOrFail()->status)->toBe(RevealStatus::Completed);

    // الحجب فُكّ لهذا الزوج فقط.
    $data = $this->actingAs($this->company->user)
        ->getJson("/api/v1/candidates/{$this->profile->ref}")->json('data');

    expect($data)->toHaveKey('name')->and($data['name'])->toBe($this->profile->user->name);

    Notification::assertSentTo($this->profile->user, ProfileRevealedNotification::class);
    $this->assertDatabaseHas('audit_logs', ['action' => 'candidate.revealed']);
});

it('★ webhook بتوقيع خاطئ لا يفكّ الحجب', function () {
    $this->actingAs($this->company->user)
        ->withHeader('Idempotency-Key', 'key-1')
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal");

    $payment = Payment::firstOrFail();
    [$body] = signedWebhook(webhook($payment->provider_ref, 75));

    // بلا هذا الفحص، أي أحد يستطيع «تأكيد» دفعةً لم تُدفَع.
    $this->call('POST', '/api/v1/payments/webhook/manual', [], [], [],
        ['HTTP_X_SIGNATURE' => 'forged', 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertStatus(401);

    expect(CandidateReveal::firstOrFail()->status)->toBe(RevealStatus::Pending);
});

it('★ webhook مُعاد التشغيل مرّتين ⇒ أثرٌ واحد', function () {
    Notification::fake();

    $this->actingAs($this->company->user)
        ->withHeader('Idempotency-Key', 'key-1')
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal");

    $payment = Payment::firstOrFail();
    [$body, $signature] = signedWebhook(webhook($payment->provider_ref, 75));

    foreach (range(1, 3) as $_) {
        $this->call('POST', '/api/v1/payments/webhook/manual', [], [], [],
            ['HTTP_X_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertOk();
    }

    expect(CandidateReveal::count())->toBe(1);

    // إشعارٌ واحد لا ثلاثة.
    Notification::assertSentToTimes($this->profile->user, ProfileRevealedNotification::class, 1);
    expect(AuditLog::where('action', 'candidate.revealed')->count())->toBe(1);
});

it('★ webhook بمبلغٍ مخالف لا يفكّ الحجب', function () {
    $this->actingAs($this->company->user)
        ->withHeader('Idempotency-Key', 'key-1')
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal");

    $payment = Payment::firstOrFail();
    [$body, $signature] = signedWebhook(webhook($payment->provider_ref, 1));

    $this->call('POST', '/api/v1/payments/webhook/manual', [], [], [],
        ['HTTP_X_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertStatus(422);

    expect(CandidateReveal::firstOrFail()->status)->toBe(RevealStatus::Pending);
});

it('★ كشفٌ سابق صالح يُعيد 200 بلا خصمٍ جديد', function () {
    CandidateReveal::factory()->completed()->create([
        'company_profile_id' => $this->company->id,
        'employee_profile_id' => $this->profile->id,
    ]);

    $this->actingAs($this->company->user)
        ->withHeader('Idempotency-Key', 'key-new')
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal")
        ->assertOk();

    expect(Payment::count())->toBe(0);   // لا دفعة جديدة
});

it('★ ADR-006: السعر مجمّد — تغيير الإعداد لا يمسّ فاتورةً سابقة', function () {
    $this->actingAs($this->company->user)
        ->withHeader('Idempotency-Key', 'key-1')
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal");

    expect(CandidateReveal::firstOrFail()->amount)->toBe(75);

    app(SettingsRepository::class)->set('reveal.fee', 200);

    // الفاتورة التاريخية لا تتغيّر أثراً رجعياً.
    expect(CandidateReveal::firstOrFail()->amount)->toBe(75)
        ->and(Payment::firstOrFail()->amount)->toBe(75);
});

it('★ ADR-010: الاسترداد يُعيد الحجب، ويسمح بكشفٍ جديد مدفوع', function () {
    $reveal = CandidateReveal::factory()->completed()->create([
        'company_profile_id' => $this->company->id,
        'employee_profile_id' => $this->profile->id,
    ]);

    app(RevealService::class)->refund($reveal);

    expect($reveal->fresh()->status)->toBe(RevealStatus::Refunded);

    // الحجب عاد.
    $data = $this->actingAs($this->company->user)
        ->getJson("/api/v1/candidates/{$this->profile->ref}")->json('data');
    expect($data)->not->toHaveKey('name');

    // والخانة تحرّرت: كشفٌ جديد ممكن (العمود المولَّد صار NULL).
    $this->actingAs($this->company->user)
        ->withHeader('Idempotency-Key', 'key-after-refund')
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal")
        ->assertStatus(202);

    $this->assertDatabaseHas('audit_logs', ['action' => 'payment.refunded']);
});

it('★ عمى صاحب العمل يسبق المال: 404 لا كشف', function () {
    $own = EmployeeProfile::factory()->employedBy($this->company->id)->create();

    $this->actingAs($this->company->user)
        ->withHeader('Idempotency-Key', 'key-1')
        ->postJson("/api/v1/candidates/{$own->ref}/reveal")
        ->assertNotFound();

    expect(Payment::count())->toBe(0);
});

it('★ الإشعار يُكتب فعلاً في قاعدة البيانات (بلا تزييف)', function () {
    // Notification::fake() يخفي غياب جدول `notifications` — وقد أخفاه فعلاً
    // حتى كشفه تشغيلٌ حيّ. هذا الاختبار لا يزيّف شيئاً.
    $this->actingAs($this->company->user)
        ->withHeader('Idempotency-Key', 'key-1')
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal");

    $payment = Payment::firstOrFail();
    [$body, $signature] = signedWebhook(webhook($payment->provider_ref, 75));

    $this->call('POST', '/api/v1/payments/webhook/manual', [], [], [],
        ['HTTP_X_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertOk();

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $this->profile->user->id,
        'type' => ProfileRevealedNotification::class,
    ]);
});

it('حساب موظّف لا يستطيع الكشف', function () {
    $employee = EmployeeProfile::factory()->create();

    $this->actingAs($employee->user)
        ->withHeader('Idempotency-Key', 'k')
        ->postJson("/api/v1/candidates/{$this->profile->ref}/reveal")
        ->assertForbidden();
});
