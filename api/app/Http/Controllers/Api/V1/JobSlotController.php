<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Jobs\JobSlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * شراء خانة وظيفة إضافية.
 *
 * ★ تُنشَأ دفعةٌ `pending` وحدها. الخانة **لا تُمنَح هنا** — تُمنَح بعد أن
 *   يستقرّ المال في الـ webhook. لا خانةَ بلا دفع، ولا دفعَ بلا خانة: نفس
 *   ضمانات الكشف حرفياً.
 */
final class JobSlotController extends Controller
{
    public function __construct(private readonly JobSlotService $slots) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $company = $user->companyProfile;

        abort_if($company === null, 403, 'متاح لحسابات الشركات فقط.');

        // مفتاح التعطيل إلزامي كالكشف: بلا مفتاحٍ يُنشئ ضغطان متتاليان دفعتين.
        $key = (string) $request->header('Idempotency-Key', '');

        if ($key === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'ترويسة Idempotency-Key إلزامية.',
            ]);
        }

        // ⚠️ لا نحجب هنا على `billing.under_development`: ذاك **إشارةُ عرضٍ**
        //    للواجهة (لافتة «الدفع قيد التطوير») لا حاجزاً — ومسار الكشف لا
        //    يحجب عليه. البوّابة نفسها هي التي ترفض في الإنتاج قبل أن تُربَط.
        $result = $this->slots->purchaseSlot($company, $key);

        return response()->json([
            'message' => 'أُنشئت الفاتورة. تُضاف الخانة فور استقرار الدفع.',
            'payment_url' => $result['payment_url'],
            'amount' => $result['payment']->amount,
        ], 202);
    }
}
