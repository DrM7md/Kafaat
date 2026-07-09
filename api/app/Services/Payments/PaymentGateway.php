<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * عقد بوّابة الدفع.
 *
 * ⚠️ لا نخزّن بيانات بطاقات إطلاقاً (PCI-DSS). البوّابة تستضيف صفحة الدفع،
 * ونحن نحفظ `provider_ref` فقط. Brain/09 §7.
 *
 * البوّابة الحقيقية (QPay/Dibsy/Stripe) قرارٌ تجاري معلّق. حتى يُحسم،
 * يعمل السائق `ManualGateway` محلّياً بلا مالٍ حقيقي.
 */
interface PaymentGateway
{
    public function name(): string;

    /** يُنشئ نيّة دفع ويُعيد رابط الصفحة المستضافة. */
    public function createIntent(Payment $payment): PaymentIntent;

    /**
     * يتحقّق من توقيع الـ webhook.
     *
     * ⚠️ webhook بلا تحقّق توقيع = أي أحد يستطيع «تأكيد» دفعةً لم تُدفَع.
     */
    public function verifySignature(string $payload, string $signature): bool;

    /**
     * يفكّ حمولة الـ webhook إلى حدثٍ مفهوم.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): WebhookEvent;

    public function refund(Payment $payment, int $amount): bool;
}
