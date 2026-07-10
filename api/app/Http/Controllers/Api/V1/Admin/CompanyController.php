<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Api\V1\Admin\Concerns\HandlesDataTable;
use App\Http\Controllers\Controller;
use App\Models\CandidateReveal;
use App\Models\CompanyProfile;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CompanyController extends Controller
{
    use HandlesDataTable;

    private const SORTABLE = ['legal_name', 'verification_status', 'created_at', 'reveals_count'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = CompanyProfile::query()
            ->with('user:id,name,email,status')
            ->withCount([
                'reveals',
                'reveals as completed_reveals_count' => fn (Builder $q) => $q->where('status', 'completed'),
            ])
            ->when($request->filled('status'), fn (Builder $q) => $q->where('verification_status', $request->string('status')))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = $request->string('q')->trim()->value();

                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('legal_name', 'like', "%{$term}%")
                        ->orWhere('cr_number', 'like', "%{$term}%")
                        ->orWhereHas('user', fn (Builder $u) => $u->where('email', 'like', "%{$term}%"));
                });
            });

        $companies = $this->paginateTable($request, $query, self::SORTABLE, 'created_at');

        return response()->json([
            'data' => array_map($this->row(...), $companies->items()),
            'meta' => $this->tableMeta($companies),
        ]);
    }

    public function show(CompanyProfile $company): JsonResponse
    {
        $company->load(['user:id,name,email,phone,status,created_at', 'industry', 'size']);

        // «طلبات الشركة» = عمليات الكشف التي طلبتها، بحالتها ومبالغها المجمّدة.
        $reveals = CandidateReveal::query()
            ->where('company_profile_id', $company->id)
            ->with(['employeeProfile:id,ref,title', 'payment:id,provider,provider_ref,status'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'company' => [
                ...$this->row($company),
                'phone' => $company->user->phone,
                'industry' => $company->industry?->label_ar,
                'size' => $company->size?->label_ar,
                'verified_at' => $company->verified_at?->toIso8601String(),
            ],
            'reveals' => $reveals->map(fn (CandidateReveal $r): array => [
                'id' => $r->id,
                'status' => $r->status->value,
                'amount' => $r->amount,       // ADR-006: مجمّد وقت الكشف
                'currency' => $r->currency,
                'source' => $r->source->value,
                'revealed_at' => $r->revealed_at?->toIso8601String(),
                'created_at' => $r->created_at?->toIso8601String(),
                'candidate' => [
                    'ref' => $r->employeeProfile->ref,
                    'title' => $r->employeeProfile->title,
                ],
                'payment' => $r->payment === null ? null : [
                    'provider' => $r->payment->provider,
                    'status' => $r->payment->status->value,
                ],
            ]),
            'stats' => [
                'total_reveals' => $reveals->count(),
                'completed' => $reveals->where('status.value', 'completed')->count(),
                'total_spent' => $reveals->where('status.value', 'completed')->sum('amount'),
            ],
        ]);
    }

    /** توثيق الشركة أو رفضها. */
    public function verify(Request $request, CompanyProfile $company): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:verified,rejected,pending,unverified'],
        ]);

        $status = VerificationStatus::from((string) $data['status']);
        $before = $company->verification_status->value;

        $company->forceFill([
            'verification_status' => $status,
            'verified_at' => $status === VerificationStatus::Verified ? now() : null,
        ])->save();

        $this->audit->log('company.verification_updated', $company, [
            'before' => $before,
            'after' => $status->value,
        ]);

        return response()->json(['message' => 'حُدِّثت حالة التوثيق.', 'status' => $status->value]);
    }

    public function destroy(CompanyProfile $company): JsonResponse
    {
        $this->deleteMany([$company->id]);

        return response()->json(['message' => 'حُذفت الشركة.']);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $this->bulkIds($request, 'company_profiles');

        $this->deleteMany($ids);

        return response()->json(['message' => 'حُذفت '.count($ids).' شركة.', 'deleted' => count($ids)]);
    }

    /**
     * حذف ناعم للشركة وحسابها.
     *
     * الصفوف المالية (`payments`, `candidate_reveals`) تبقى: نزاهة الفواتير
     * تسبق نظافة الجدول (ADR-012). المفاتيح الأجنبية `restrictOnDelete`
     * تحرس ذلك حتى لو حاول أحدٌ الحذف الصلب.
     *
     * @param  list<int>  $ids
     */
    private function deleteMany(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            CompanyProfile::query()->whereIn('id', $ids)->get()->each(function (CompanyProfile $company) {
                $this->audit->log('company.deleted', $company, ['legal_name' => $company->legal_name]);

                $company->user->delete();
                $company->delete();
            });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function row(CompanyProfile $company): array
    {
        return [
            'id' => $company->id,
            'legal_name' => $company->legal_name,
            'brand_name' => $company->brand_name,
            'cr_number' => $company->cr_number,
            'verification_status' => $company->verification_status->value,
            'email' => $company->user->email,
            'contact_name' => $company->user->name,
            'account_status' => $company->user->status->value,
            'reveals_count' => (int) ($company->reveals_count ?? 0),
            'completed_reveals_count' => (int) ($company->completed_reveals_count ?? 0),
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }
}
