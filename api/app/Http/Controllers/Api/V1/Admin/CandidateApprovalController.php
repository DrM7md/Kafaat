<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Api\V1\Admin\Concerns\HandlesDataTable;
use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\Events;
use App\Services\Notifications\Notifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * طلبات اعتماد الملفّات المهنية.
 *
 * ★ القائمة **محجوبة**: لا اسم، ولا بريد، ولا هاتف.
 *
 *   من يملك `profile.approve` يراجع جودة الملفّ — المسمّى، المجال، الخبرة،
 *   النبذة، المهارات، الاكتمال — لا هويّة صاحبه. ولو أردنا كشف الهوية
 *   للمراجعة، لمنحنا كل مراجعٍ المنتجَ مجّاناً.
 *
 *   من يحتاج رؤية الهويّة (مثلاً للتحقّق من مستند) يفتح `/candidates/{ref}`
 *   بصلاحية `profile.moderate` — وتُسجَّل عليه في `audit_logs`.
 */
final class CandidateApprovalController extends Controller
{
    use HandlesDataTable;

    private const SORTABLE = ['submitted_at', 'created_at', 'completeness', 'years_experience', 'title'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = EmployeeProfile::query()
            ->with(['field:id,label_ar', 'city:id,label_ar', 'employmentStatus:id,label_ar,meta', 'skills:id,name'])
            ->withCount('attachments')
            ->when($request->filled('status'), fn (Builder $q) => $q->where('approval_status', $request->string('status')))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = $request->string('q')->trim()->value();

                // ⚠️ لا بحث في `users.name`. الاستعلام قناة تسريب كالاستجابة.
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('title', 'like', "%{$term}%")
                        ->orWhere('ref', 'like', "%{$term}%")
                        ->orWhereHas('skills', fn (Builder $s) => $s->where('name', 'like', "%{$term}%"));
                });
            });

        $profiles = $this->paginateTable($request, $query, self::SORTABLE, 'submitted_at');

        return response()->json([
            'data' => array_map($this->row(...), $profiles->items()),
            'meta' => $this->tableMeta($profiles),
            'counts' => $this->statusCounts(),
        ]);
    }

    public function approve(Request $request, EmployeeProfile $profile): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $profile->forceFill([
            'approval_status' => ApprovalStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $actor->id,
            'rejection_reason' => null,
        ])->save();

        $this->audit->log('profile.approved', $profile, ['ref' => $profile->ref]);

        $this->notifier->send($profile->user, Events::profileApproved());

        return response()->json(['message' => 'اعتُمد الملفّ وأصبح ظاهراً للشركات.']);
    }

    public function reject(Request $request, EmployeeProfile $profile): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $profile->forceFill([
            'approval_status' => ApprovalStatus::Rejected,
            'approved_at' => null,
            'approved_by' => null,
            'rejection_reason' => $data['reason'],
        ])->save();

        $this->audit->log('profile.rejected', $profile, [
            'ref' => $profile->ref,
            'reason' => $data['reason'],
        ]);

        // الرسالة كانت تَعِد بأنّ «صاحبه أُبلغ» — ولم يكن يُبلَّغ أحد.
        $this->notifier->send($profile->user, Events::profileRejected($data['reason']));

        return response()->json(['message' => 'رُفض الطلب وأُبلغ صاحبه بالسبب.']);
    }

    public function destroy(EmployeeProfile $profile): JsonResponse
    {
        $this->deleteMany([$profile->id]);

        return response()->json(['message' => 'حُذف الملفّ.']);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $this->bulkIds($request, 'employee_profiles');

        $this->deleteMany($ids);

        return response()->json(['message' => 'حُذف '.count($ids).' ملفّ.', 'deleted' => count($ids)]);
    }

    /**
     * حذف ناعم. الصفوف المالية تبقى — نزاهة الفواتير تسبق نظافة الجدول.
     *
     * @param  list<int>  $ids
     */
    private function deleteMany(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            EmployeeProfile::query()->whereIn('id', $ids)->get()->each(function (EmployeeProfile $profile) {
                $this->audit->log('profile.deleted', $profile, ['ref' => $profile->ref]);
                $profile->delete();
            });
        });
    }

    /** @return array<string, int> */
    private function statusCounts(): array
    {
        /** @var array<string, int> $counts */
        $counts = EmployeeProfile::query()
            ->selectRaw('approval_status, COUNT(*) as total')
            ->groupBy('approval_status')
            ->pluck('total', 'approval_status')
            ->all();

        return array_merge(
            array_fill_keys(array_column(ApprovalStatus::cases(), 'value'), 0),
            $counts,
        );
    }

    /**
     * ★ لا `name`، ولا `email`، ولا `phone`. القائمة محجوبة عمداً.
     *
     * @return array<string, mixed>
     */
    private function row(EmployeeProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'ref' => $profile->ref,
            'title' => $profile->title,
            'field' => $profile->field?->label_ar,
            'city' => $profile->city?->label_ar,
            'years_experience' => $profile->years_experience,
            'employment_status' => $profile->employmentStatus === null ? null : [
                'label' => $profile->employmentStatus->label_ar,
                'meta' => $profile->employmentStatus->meta,
            ],
            'skills' => $profile->skills->pluck('name')->all(),
            'attachments_count' => (int) ($profile->attachments_count ?? 0),
            'completeness' => $profile->completeness,
            'approval_status' => $profile->approval_status->value,
            'rejection_reason' => $profile->rejection_reason,
            'submitted_at' => $profile->submitted_at?->toIso8601String(),
            'created_at' => $profile->created_at?->toIso8601String(),
        ];
    }
}
