<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\JobPostingResource;
use App\Models\CompanyProfile;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Jobs\JobSlotService;
use App\Support\SettingKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * وظائف الشركة — نشرُها وإدارتها ضمن الحصّة.
 *
 * ★ الحصّة تُفرَض في `JobSlotService` لا هنا: لو فُرضت في المتحكّم لتجاوزها
 *   مسارٌ آخر، ولو حُسبت في مكانين لتباعد المنطقان.
 */
final class JobPostingController extends Controller
{
    public function __construct(
        private readonly JobSlotService $slots,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $company = $this->ownCompany($request);

        $jobs = JobPosting::query()
            ->where('company_profile_id', $company->id)
            ->with(['field', 'city'])
            ->withCount(['interests as interests_count' => fn ($q) => $q->whereNull('withdrawn_at')])

            // ما لم تره الشركةُ بعد — شارةُ «جديد» على البطاقة.
            ->withCount(['interests as new_interests_count' => fn ($q) => $q->whereNull('withdrawn_at')->whereNull('seen_at')])
            ->orderByRaw("FIELD(status, 'open', 'draft', 'closed')")
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $jobs->map(fn (JobPosting $job) => JobPostingResource::make($job)->asOwner()),
            'slots' => $this->slots->summary($company),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertEnabled();

        $company = $this->ownCompany($request);

        $job = new JobPosting($this->validated($request));
        $job->company_profile_id = $company->id;
        $job->status = JobStatus::Draft;
        $job->currency = (string) settings(SettingKeys::BILLING_CURRENCY, 'QAR');
        $job->save();

        return response()->json([
            'job' => JobPostingResource::make($job)->asOwner(),
        ], 201);
    }

    public function update(Request $request, JobPosting $job): JsonResponse
    {
        $this->assertOwns($request, $job);

        $job->fill($this->validated($request))->save();

        return response()->json(['job' => JobPostingResource::make($job->fresh())->asOwner()]);
    }

    /**
     * فتح الوظيفة — أي إظهارها للباحثين، وشغلُ خانةٍ من الحصّة.
     *
     * ★ نفدت الحصّة ⇒ 402 لا 422: «ادفع لتُكمل» لا «طلبك خاطئ». والواجهة
     *   تعرض عليها شراء خانة، ومعها ثمنُها.
     */
    public function open(Request $request, JobPosting $job): JsonResponse
    {
        $this->assertEnabled();
        $company = $this->assertOwns($request, $job);

        if ($job->isOpen()) {
            return response()->json(['job' => JobPostingResource::make($job)->asOwner()]);
        }

        if (! $this->slots->open($job)) {
            $summary = $this->slots->summary($company);

            return response()->json([
                'message' => "استنفدتَ حصّتك ({$summary['total']} وظيفة مفتوحة). أغلق وظيفةً، أو اشترِ خانةً إضافية.",
                'slots' => $summary,
            ], 402);
        }

        $this->audit->log(AuditLogger::JOB_OPENED, $job, [
            'ref' => $job->ref,
            'is_paid_slot' => $job->fresh()->is_paid_slot,
        ]);

        return response()->json([
            'job' => JobPostingResource::make($job->fresh())->asOwner(),
            'slots' => $this->slots->summary($company),
        ]);
    }

    /** الإغلاق يحرّر الخانة — وهو المقصود: نظّف لوحتك تكسب خانة. */
    public function close(Request $request, JobPosting $job): JsonResponse
    {
        $company = $this->assertOwns($request, $job);

        $job->forceFill([
            'status' => JobStatus::Closed,
            'closed_at' => now(),
        ])->save();

        $this->audit->log(AuditLogger::JOB_CLOSED, $job, ['ref' => $job->ref]);

        return response()->json([
            'job' => JobPostingResource::make($job->fresh())->asOwner(),
            'slots' => $this->slots->summary($company),
        ]);
    }

    public function destroy(Request $request, JobPosting $job): JsonResponse
    {
        $this->assertOwns($request, $job);

        // حذفٌ ناعم: الإعجابات تُشير إليها، وشركةٌ قد تكون دفعت رسم كشفٍ
        // بناءً على أحدها. محوُ الوظيفة يجعل تلك الفاتورة بلا سبب.
        $job->delete();

        return response()->json(['message' => 'حُذفت الوظيفة.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'field_id' => ['nullable', 'exists:lookups,id'],
            'country_id' => ['nullable', 'exists:lookups,id'],
            'city_id' => ['nullable', 'exists:lookups,id'],
            'min_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'salary_min' => ['nullable', 'integer', 'min:0'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'gte:salary_min'],
        ]);
    }

    private function ownCompany(Request $request): CompanyProfile
    {
        /** @var User $user */
        $user = $request->user();

        $company = $user->companyProfile;

        abort_if($company === null, 403, 'متاح لحسابات الشركات فقط.');

        return $company;
    }

    /** 404 لا 403: وجودُ وظيفةِ منافسٍ معلومةٌ لا تُمنَح. */
    private function assertOwns(Request $request, JobPosting $job): CompanyProfile
    {
        $company = $this->ownCompany($request);

        abort_unless($job->company_profile_id === $company->id, 404);

        return $company;
    }

    private function assertEnabled(): void
    {
        if (! (bool) settings(SettingKeys::FEATURE_JOBS, true)) {
            throw ValidationException::withMessages([
                'jobs' => 'قسم الوظائف معطّل حالياً.',
            ]);
        }
    }
}
