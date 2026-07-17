<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RevealStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MaskedProfileResource;
use App\Models\EmployeeProfile;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\Masking\ProfileVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * المرشّحون الذين أبدوا إعجابهم بوظيفة.
 *
 * ★★ الحاجز نفسه، بلا استثناء.
 *
 *    نُعيد `MaskedProfileResource` — الـ Resource ذاته الذي يخدم التصفّح. فكل
 *    ما بُني للحجب يسري هنا تلقائياً: الاسم والهاتف والبريد **تختفي من JSON**
 *    حتى يُدفع رسم الكشف، وعمى صاحب العمل يُطبَّق، والحقول المحجوبة تُحذف لا
 *    تُشوَّش.
 *
 *    ولو بنينا لقائمة المعجبين Resource خاصّاً لتباعد عن قواعد الحجب مع أوّل
 *    تعديل — وصار الإعجابُ باباً خلفياً يُفرَّغ منه نموذج الربح.
 *
 *    ما يبيعه الإعجاب هو **النيّة**: الشركة تعرف أنّ هذا المرشّح يريد وظيفتها،
 *    فتدفع رسم الكشف عن رغبةٍ لا مخاطرة.
 */
final class JobInterestController extends Controller
{
    public function __construct(private readonly ProfileVisibilityService $visibility) {}

    public function __invoke(Request $request, JobPosting $job): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $company = $user->companyProfile;

        // 404 لا 403: وجودُ وظيفةِ منافسٍ معلومةٌ لا تُمنَح.
        abort_if($company === null, 403, 'متاح لحسابات الشركات فقط.');
        abort_unless($job->company_profile_id === $company->id, 404);

        $profiles = EmployeeProfile::query()
            ->whereHas('jobInterests', fn ($q) => $q
                ->where('job_posting_id', $job->id)
                ->whereNull('withdrawn_at'))
            ->browsable()
            ->with(['user', 'field', 'city', 'nationality', 'employmentStatus', 'skills'])
            ->withCount(['reveals as reveals_count' => fn ($q) => $q->where('status', RevealStatus::Completed)])
            ->get();

        // استعلامٌ واحد لكل القائمة بدل استعلامٍ لكل مرشّح داخل الـ Resource.
        $this->visibility->primeReveals($company, $profiles->all());

        // عُلِّمت مقروءةً: شارةُ «جديد» على بطاقة الوظيفة تختفي.
        $job->interests()->whereNull('seen_at')->update(['seen_at' => now()]);

        return response()->json([
            'job' => ['ref' => $job->ref, 'title' => $job->title],
            'data' => MaskedProfileResource::collection($profiles),
        ]);
    }
}
