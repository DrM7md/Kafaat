<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobPostingResource;
use App\Models\EmployeeProfile;
use App\Models\JobInterest;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\Notifications\Events;
use App\Services\Notifications\Notifier;
use App\Support\SettingKeys;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * لوحة الوظائف — ما يراه الباحث عن عمل، وإبداءُ إعجابه.
 *
 * ★★ الإعجاب **لا يكشف الهوية**. الشركة تُبلَّغ بأنّ «مرشّحاً» أُعجب، وترى
 *    بطاقةً محجوبة وزرَّ كشف. ولولا ذلك لانهار نموذج الربح: تكفي الشركةَ أن
 *    تنشر وظيفةً فيأتيها الناس بأسمائهم بلا ريال.
 */
final class JobBoardController extends Controller
{
    public function __construct(private readonly Notifier $notifier) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertEnabled();

        $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'field' => ['nullable', 'integer'],
            'city' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $profile = $this->ownProfile($request);

        $query = JobPosting::query()
            ->open()
            ->with(['company:id,legal_name,brand_name', 'field', 'city'])

            // هل أُعجبتُ بها؟ الزرّ يعرف حالته بلا استعلامٍ لكل بطاقة.
            ->withExists(['interests as has_interest' => fn (Builder $q) => $q
                ->where('employee_profile_id', $profile->id)
                ->whereNull('withdrawn_at'),
            ]);

        // ★ عمى صاحب العمل — بالاتجاه المعاكس.
        //
        //   لا نُري الموظّفَ وظائفَ شركته الحالية: لو أُعجب بإحداها لوصل شركتَه
        //   إشعارٌ بأنّ «مرشّحاً» يريد الرحيل — ثم لا تستطيع كشفَه (الحجب يمنعها)،
        //   فتبقى بطاقةٌ عمياء تُنذرها بأنّ أحد موظّفيها يبحث. تسريبٌ بالاستدلال.
        if ($profile->current_employer_company_id !== null) {
            $query->where('company_profile_id', '!=', $profile->current_employer_company_id);
        }

        if ($term = $request->string('q')->trim()->value()) {
            $term = addcslashes($term, '%_\\');
            $query->where(fn (Builder $q) => $q
                ->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%"));
        }

        if ($field = $request->integer('field')) {
            $query->where('field_id', $field);
        }

        if ($city = $request->integer('city')) {
            $query->where('city_id', $city);
        }

        $jobs = $query->orderByDesc('published_at')
            ->paginate((int) $request->integer('per_page', 20))
            ->withQueryString();

        return response()->json([
            'data' => JobPostingResource::collection($jobs->items()),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }

    public function show(Request $request, JobPosting $job): JsonResponse
    {
        $this->assertEnabled();

        $profile = $this->ownProfile($request);

        // مغلقةٌ أو مسودّة، أو وظيفةُ شركته الحالية ⇒ 404. الوجود نفسه سرّ.
        abort_unless($job->isOpen(), 404);
        abort_if($job->company_profile_id === $profile->current_employer_company_id, 404);

        $job->load(['company:id,legal_name,brand_name', 'field', 'city']);

        $job->setAttribute('has_interest', JobInterest::query()
            ->where('job_posting_id', $job->id)
            ->where('employee_profile_id', $profile->id)
            ->active()
            ->exists());

        return response()->json(['job' => JobPostingResource::make($job)]);
    }

    /**
     * أبدِ إعجابك.
     *
     * ★★ لا يُرسَل اسمٌ ولا رقم. الشركة تُبلَّغ بوصول مهتمّ، وتفتح قائمة
     *    المعجبين فترى بطاقةً محجوبة وزرَّ كشف — كالتصفّح تماماً.
     */
    public function interest(Request $request, JobPosting $job): JsonResponse
    {
        $this->assertEnabled();

        $profile = $this->ownProfile($request);

        abort_unless($job->isOpen(), 404);
        abort_if($job->company_profile_id === $profile->current_employer_company_id, 404);

        // ملفٌّ غير منشور لا يستطيع أن يُعجَب: الشركة لن ترى شيئاً حين تفتحه.
        if (! $profile->isPublished()) {
            throw ValidationException::withMessages([
                'profile' => 'انشر ملفّك أوّلاً — الشركة لن ترى ملفّاً غير منشور.',
            ]);
        }

        $interest = JobInterest::query()->firstOrNew([
            'job_posting_id' => $job->id,
            'employee_profile_id' => $profile->id,
        ]);

        // الضغط مرّتين لا يُنشئ صفّين ولا يُرسل إشعارين. والعودة بعد انسحابٍ
        // تُعيد إحياء الصفّ نفسه.
        $wasActive = $interest->exists && $interest->isActive();

        $interest->withdrawn_at = null;
        $interest->save();

        if (! $wasActive) {
            $job->loadMissing('company.user');

            $this->notifier->send(
                $job->company->user,
                Events::jobInterest($job->title, $job->ref),
            );
        }

        return response()->json(['message' => 'أُبلغت الشركةُ باهتمامك. هويّتك ما زالت محجوبة.']);
    }

    /** سحب الإعجاب. لا يُحذف الصفّ: قد تكون الشركة دفعت رسم كشفٍ بناءً عليه. */
    public function withdraw(Request $request, JobPosting $job): JsonResponse
    {
        $profile = $this->ownProfile($request);

        JobInterest::query()
            ->where('job_posting_id', $job->id)
            ->where('employee_profile_id', $profile->id)
            ->update(['withdrawn_at' => now()]);

        return response()->json(['message' => 'سُحب إعجابك.']);
    }

    private function ownProfile(Request $request): EmployeeProfile
    {
        /** @var User $user */
        $user = $request->user();

        $profile = $user->employeeProfile;

        abort_if($profile === null, 403, 'متاح لحسابات الموظّفين فقط.');

        return $profile;
    }

    private function assertEnabled(): void
    {
        if (! (bool) settings(SettingKeys::FEATURE_JOBS, true)) {
            throw ValidationException::withMessages(['jobs' => 'قسم الوظائف معطّل حالياً.']);
        }
    }
}
