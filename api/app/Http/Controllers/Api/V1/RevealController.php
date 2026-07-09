<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaskedProfileResource;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Reveal\RevealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class RevealController extends Controller
{
    public function __construct(private readonly RevealService $reveals) {}

    public function __invoke(Request $request, EmployeeProfile $profile): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $company = $user->companyProfile;

        abort_if($company === null, 403, 'الكشف متاحٌ لحسابات الشركات فقط.');
        abort_unless($profile->isPublished(), 404);

        // مفتاح المرّة الواحدة إلزامي: بلا هذا، ضغطةٌ مزدوجة على الزرّ
        // أو إعادة إرسالٍ من الشبكة تُنشئ دفعتين.
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || trim($key) === '') {
            throw ValidationException::withMessages([
                'Idempotency-Key' => 'ترويسة Idempotency-Key مطلوبة لعمليات الكشف.',
            ]);
        }

        $result = $this->reveals->reveal($company, $profile, trim($key));

        if ($result->outcome === 'awaiting_payment') {
            return response()->json([
                'message' => 'بانتظار إتمام الدفع.',
                'payment_url' => $result->paymentUrl,
                'reveal_id' => $result->reveal->id,
                // الحجب لم يُفكّ بعد. لا نُرسل الملفّ هنا.
            ], 202);
        }

        $profile->load(['user', 'field', 'city', 'employmentStatus', 'skills', 'attachments']);

        return MaskedProfileResource::make($profile)
            ->detailed()
            ->response()
            ->setStatusCode($result->httpStatus());
    }
}
