<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\PaymentsUnderDevelopmentException;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * بوّابة تطوير — بلا مالٍ حقيقي.
 *
 * تُبقي المشروع كاملاً وقابلاً للتشغيل والاختبار end-to-end ريثما يُحسم
 * قرار البوّابة التجارية (QPay / Dibsy / Stripe). واجهتها مطابقة للعقد،
 * فاستبدالها لاحقاً تغييرُ سطرٍ في الإعدادات لا إعادةُ كتابة.
 *
 * ⚠️ يرفض العمل في الإنتاج. `assertNotProduction()` حارسٌ متعمَّد:
 *    بوّابةٌ تؤكّد أي دفعةٍ بلا مال هي ثغرة مجّانية.
 */
final class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function createIntent(Payment $payment): PaymentIntent
    {
        $this->assertNotProduction();

        $ref = 'manual_'.Str::lower(Str::random(24));

        return new PaymentIntent(
            providerRef: $ref,
            paymentUrl: config('app.frontend_url').'/payments/simulate?ref='.$ref,
        );
    }

    /**
     * توقيع HMAC على الحمولة الخام. المقارنة بـ hash_equals لا `===`
     * لتفادي هجوم التوقيت.
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        $this->assertNotProduction();

        $expected = hash_hmac('sha256', $payload, $this->secret());

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): WebhookEvent
    {
        return new WebhookEvent(
            type: (string) ($payload['type'] ?? 'payment.failed'),
            providerRef: (string) ($payload['provider_ref'] ?? ''),
            amount: (int) ($payload['amount'] ?? 0),
        );
    }

    public function refund(Payment $payment, int $amount): bool
    {
        $this->assertNotProduction();

        return true;
    }

    /**
     * لا سرَّ ثابتاً في المستودع: سرٌّ معروفٌ للعالم يعني webhook مزوَّراً.
     *
     * إن لم يُضبط `MANUAL_GATEWAY_SECRET` اشتُقَّ من `APP_KEY` — فيبقى
     * فريداً لكل بيئة، وثابتاً بين الطلبات، بلا خطوة إعدادٍ إضافية للمطوّر.
     */
    public function secret(): string
    {
        $configured = (string) config('services.manual_gateway.secret', '');

        if ($configured !== '') {
            return $configured;
        }

        return hash('sha256', 'kafaat.manual_gateway|'.(string) config('app.key'));
    }

    private function assertNotProduction(): void
    {
        if (app()->isProduction()) {
            throw new PaymentsUnderDevelopmentException(
                'بوّابة التطوير لا تعمل في الإنتاج.'
            );
        }
    }
}
