<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use App\Services\Reveal\RevealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ⚠️ مسارٌ بلا مصادقة — وبتحقّق توقيعٍ إلزامي.
 *
 * webhook بلا تحقّق توقيع = أي أحد يستطيع «تأكيد» دفعةً لم تُدفَع،
 * فيكشف مرشّحاً مجّاناً. هذا الفحص هو كل ما يفصل بين النظام وبين ذلك.
 */
final class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RevealService $reveals,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $signature = (string) $request->header('X-Signature', '');

        if (! $this->gateway->verifySignature($request->getContent(), $signature)) {
            return response()->json(['message' => 'توقيع غير صالح.'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $event = $this->gateway->parseWebhook($payload);

        $payment = Payment::query()->where('provider_ref', $event->providerRef)->first();

        if ($payment === null) {
            // نُعيد 200 كي لا تُعيد البوّابة المحاولة أبداً على مرجعٍ مجهول.
            return response()->json(['message' => 'مرجع غير معروف — تُجوهل.']);
        }

        if (! $event->succeeded()) {
            $payment->update(['status' => PaymentStatus::Failed, 'payload' => $payload]);

            return response()->json(['message' => 'سُجّل فشل الدفع.']);
        }

        // مبلغ مختلف عن المتوقّع ⇒ لا نكشف. عبثٌ أو خطأ في البوّابة.
        if ($event->amount !== $payment->amount) {
            return response()->json(['message' => 'المبلغ لا يطابق الدفعة.'], 422);
        }

        $payment->update(['payload' => $payload]);

        // معاملة واحدة: paid + completed + تدقيق. آمنة لإعادة التشغيل.
        $this->reveals->completeFromPayment($payment);

        return response()->json(['message' => 'تمّت المعالجة.']);
    }
}
