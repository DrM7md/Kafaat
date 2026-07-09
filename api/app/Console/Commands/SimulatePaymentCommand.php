<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\ManualGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * يحاكي تأكيد البوّابة محلّياً بإرسال webhook موقّعٍ إلى المسار الحقيقي.
 *
 * لا يكتب في قاعدة البيانات مباشرةً عمداً: نريد أن يمرّ التدفّق كاملاً
 * عبر تحقّق التوقيع ومنع إعادة التشغيل والمعاملة — وإلّا كنّا نختبر طريقاً
 * لا يسلكه الإنتاج.
 */
final class SimulatePaymentCommand extends Command
{
    protected $signature = 'kafaat:simulate-payment {provider_ref} {--fail : محاكاة فشل الدفع}';

    protected $description = 'محاكاة webhook الدفع محلّياً (بيئة التطوير فقط)';

    public function handle(ManualGateway $gateway): int
    {
        if (app()->isProduction()) {
            $this->error('هذا الأمر لبيئة التطوير فقط.');

            return self::FAILURE;
        }

        $ref = (string) $this->argument('provider_ref');

        $payment = Payment::query()->where('provider_ref', $ref)->first();

        if ($payment === null) {
            $this->error("لا توجد دفعة بالمرجع: {$ref}");

            return self::FAILURE;
        }

        $payload = [
            'type' => $this->option('fail') ? 'payment.failed' : 'payment.succeeded',
            'provider_ref' => $ref,
            'amount' => $payment->amount,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            $this->error('تعذّر ترميز الحمولة.');

            return self::FAILURE;
        }

        $signature = hash_hmac('sha256', $body, $gateway->secret());

        $response = Http::withBody($body, 'application/json')
            ->withHeader('X-Signature', $signature)
            ->post(config('app.url').'/api/v1/payments/webhook/manual');

        if ($response->successful()) {
            $this->info("✓ {$response->json('message')}");
            $this->line("  الدفعة: {$payment->fresh()->status->value}");

            return self::SUCCESS;
        }

        $this->error("فشل: {$response->status()} — {$response->body()}");

        return self::FAILURE;
    }
}
