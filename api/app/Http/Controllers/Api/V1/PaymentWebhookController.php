<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Jobs\JobSlotService;
use App\Services\Payments\PaymentGateway;
use App\Services\Reveal\RevealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        private readonly JobSlotService $slots,
    ) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        // البوّابة المفعّلة هي وحدها من نثق بتوقيعه. طلبٌ يدّعي بوّابةً أخرى
        // كان يُتحقَّق منه بسرّ البوّابة المفعّلة — وهو خلطٌ يصير ثغرةً يوم
        // تُضاف بوّابةٌ ثانية.
        if ($provider !== $this->gateway->name()) {
            return response()->json(['message' => 'بوّابة غير مفعّلة.'], 404);
        }

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

        $payment->update(['payload' => $payload]);

        if (! $event->succeeded()) {
            // ★ الدفعة **والكشف** معاً: كشفٌ عالق على `pending` يحجز الخانة
            //   في الفهرس الفريد، فيمنع الشركة من إعادة المحاولة إلى الأبد.
            //
            //   دفعةُ خانةٍ فاشلة لا تحجز شيئاً — الخانة رصيدٌ لا كائن.
            if ($payment->purpose === PaymentPurpose::Reveal) {
                $this->reveals->failFromPayment($payment);
            } else {
                $payment->forceFill(['status' => PaymentStatus::Failed])->save();
            }

            return response()->json(['message' => 'سُجّل فشل الدفع.']);
        }

        // مبلغ مختلف عن المتوقّع ⇒ لا نُتمّ شيئاً. عبثٌ أو خطأ في البوّابة.
        // 200 لا 422: إعادة المحاولة لن تُغيّر المبلغ، فلا نُغرق البوّابة
        // بحلقة إعادةٍ أبدية. الحدث مُسجَّل في `payload` للمراجعة.
        if ($event->amount !== $payment->amount) {
            return response()->json(['message' => 'المبلغ لا يطابق الدفعة — تُجوهل.']);
        }

        // ★★ التفريق بالغرض. قبل هذا كان الـ webhook يبحث عن كشفٍ لكل دفعة،
        //    فدفعةُ خانةِ وظيفةٍ كانت تنهار — **بعد** أن يدخل المال.
        match ($payment->purpose) {
            PaymentPurpose::Reveal => $this->reveals->completeFromPayment($payment),
            PaymentPurpose::JobSlot => $this->completeJobSlot($payment),
        };

        return response()->json(['message' => 'تمّت المعالجة.']);
    }

    /**
     * استقرّ مالُ خانةٍ إضافية.
     *
     * لا نفتح وظيفةً هنا: الخانة **رصيد** لا كائن. الشركة تستهلكه حين تفتح
     * وظيفتها التالية (`JobSlotService::open`) — فإن أغلقت التبويب بعد الدفع
     * بقي رصيدُها محفوظاً ولم يضع.
     *
     * `isSettled()` يحرس إعادة تشغيل الـ webhook: البوّابات تُعيد إرسال
     * الأحداث، وبلا هذا يُكتب أثرُ تدقيقٍ ثانٍ لمالٍ دخل مرّةً واحدة.
     */
    private function completeJobSlot(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            /** @var Payment|null $locked */
            $locked = Payment::query()->lockForUpdate()->find($payment->id);

            if ($locked === null || $locked->status->isSettled()) {
                return;
            }

            $locked->forceFill(['status' => PaymentStatus::Paid, 'paid_at' => now()])->save();

            $this->slots->markPaid($locked);
        });
    }
}
