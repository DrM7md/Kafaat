<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminLookupResource;
use App\Models\Lookup;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * إدارة القوائم المرجعية — المجالات، المدن، الدول، الحالات الوظيفية…
 *
 * هذا المتحكّم هو ما يجعل «أضِف مجالاً جديداً» أو «افتح الإمارات بدرهمها»
 * عملاً إدارياً بنقرة، لا مهاجرةً ونشرَ كود. Brain/05-settings-engine.md §4.
 *
 * يرى المشرفُ هنا ما لا يراه أحد: الاقتراحات المعلَّقة القادمة من «أخرى».
 */
final class LookupController extends Controller
{
    /** المجموعات التي يجوز للمشرف إدارتها. أي مجموعةٍ أخرى ليست قائمةً بعد. */
    private const GROUPS = [
        Lookup::FIELD,
        Lookup::CITY,
        Lookup::COUNTRY,
        Lookup::EMPLOYMENT_STATUS,
        Lookup::INDUSTRY,
        Lookup::COMPANY_SIZE,
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * كل القوائم — المعتمَد والمعلَّق والمعطَّل.
     *
     * على خلاف المسار العامّ الذي يعرض المعتمَد وحده: المشرف يحتاج أن يرى
     * ما ينتظر قراره، وإلّا بقي الاقتراح حبيس قاعدة البيانات إلى الأبد.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group' => ['nullable', Rule::in(self::GROUPS)],
            'status' => ['nullable', Rule::in([Lookup::STATUS_PENDING, Lookup::STATUS_APPROVED, Lookup::STATUS_REJECTED])],
        ]);

        $lookups = Lookup::query()
            ->with(['parent:id,label_ar', 'suggestedBy:id,name'])
            ->when(
                isset($data['group']),
                fn ($q) => $q->where('group', $data['group']),
                fn ($q) => $q->whereIn('group', self::GROUPS),
            )
            ->when(isset($data['status']), fn ($q) => $q->where('status', $data['status']))
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('label_ar')
            ->get();

        return response()->json([
            'lookups' => AdminLookupResource::collection($lookups),
            'groups' => self::GROUPS,
            'pending_count' => Lookup::query()->where('status', Lookup::STATUS_PENDING)->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group' => ['required', Rule::in(self::GROUPS)],
            'label_ar' => ['required', 'string', 'max:120'],
            'label_en' => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', 'exists:lookups,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'meta' => ['nullable', 'array'],
        ]);

        $group = (string) $data['group'];
        $label = (string) $data['label_ar'];

        $lookup = Lookup::create([
            'group' => $group,
            'key' => $this->uniqueKey($group, $label),
            'label_ar' => $label,
            'label_en' => $data['label_en'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'meta' => $data['meta'] ?? null,
            'is_active' => true,

            // ما ينشئه المشرف معتمَدٌ فوراً — هو مصدر الاعتماد لا طالبه.
            'status' => Lookup::STATUS_APPROVED,
        ]);

        $this->audit->log(AuditLogger::LOOKUP_CREATED, $lookup, [
            'group' => $group,
            'label_ar' => $label,
        ]);

        return response()->json(['lookup' => AdminLookupResource::make($lookup)], 201);
    }

    public function update(Request $request, Lookup $lookup): JsonResponse
    {
        $data = $request->validate([
            'label_ar' => ['sometimes', 'string', 'max:120'],
            'label_en' => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', 'exists:lookups,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        // قائمةٌ لا يجوز أن تكون أباً لنفسها — وإلّا دارت الهرمية إلى الأبد.
        if (($data['parent_id'] ?? null) === $lookup->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'لا يجوز أن تكون القائمة تابعةً لنفسها.',
            ]);
        }

        $before = $lookup->only(['label_ar', 'label_en', 'parent_id', 'sort_order', 'is_active', 'meta']);

        $lookup->fill($data)->save();

        $this->audit->log(AuditLogger::LOOKUP_UPDATED, $lookup, [
            'before' => $before,
            'after' => $lookup->only(['label_ar', 'label_en', 'parent_id', 'sort_order', 'is_active', 'meta']),
        ]);

        return response()->json(['lookup' => AdminLookupResource::make($lookup->fresh())]);
    }

    /**
     * اعتماد اقتراحٍ جاء من «أخرى».
     *
     * لا بيانَ يُنقل: الملفّ الذي اقترحها يشير إليها منذ لحظة كتابتها. الاعتماد
     * يفتحها للجميع فحسب.
     */
    public function approve(Request $request, Lookup $lookup): JsonResponse
    {
        $data = $request->validate([
            // للمشرف أن يُهذّب التسمية قبل أن تصير رسمية: «دوحة» ← «الدوحة».
            'label_ar' => ['sometimes', 'string', 'max:120'],
            'parent_id' => ['nullable', 'exists:lookups,id'],
        ]);

        $this->assertPending($lookup);

        $lookup->fill($data)->forceFill([
            'status' => Lookup::STATUS_APPROVED,
            'is_active' => true,
        ])->save();

        $this->audit->log(AuditLogger::LOOKUP_SUGGESTION_APPROVED, $lookup, [
            'group' => $lookup->group,
            'label_ar' => $lookup->label_ar,
            'suggested_by' => $lookup->suggested_by_user_id,
        ]);

        return response()->json(['lookup' => AdminLookupResource::make($lookup->fresh())]);
    }

    /**
     * رفض اقتراح.
     *
     * لا يُحذف الصفّ: ملفُّ الموظّف يشير إليه بمفتاحٍ أجنبي، وحذفُه يُفرّغ
     * حقلَه بلا إنذار. يبقى مرفوضاً — يراه صاحبه ولا يراه سواه.
     */
    public function reject(Lookup $lookup): JsonResponse
    {
        $this->assertPending($lookup);

        $lookup->forceFill([
            'status' => Lookup::STATUS_REJECTED,
            'is_active' => false,
        ])->save();

        $this->audit->log(AuditLogger::LOOKUP_SUGGESTION_REJECTED, $lookup, [
            'group' => $lookup->group,
            'label_ar' => $lookup->label_ar,
        ]);

        return response()->json(['lookup' => AdminLookupResource::make($lookup->fresh())]);
    }

    public function destroy(Lookup $lookup): JsonResponse
    {
        // الحذف يُفرّغ حقلَ كل ملفٍّ يشير إليها (nullOnDelete) — قرارٌ لا رجعة
        // فيه. التعطيل يُخفيها من النماذج ويُبقي بيانات من اختارها سليمة.
        $inUse = $lookup->children()->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'lookup' => 'تتبع هذه القائمةَ قوائمُ أخرى. عطّلها بدل حذفها، أو انقل تابعيها أولاً.',
            ]);
        }

        $snapshot = $lookup->only(['group', 'key', 'label_ar']);

        $this->audit->log(AuditLogger::LOOKUP_DELETED, $lookup, $snapshot);

        $lookup->delete();

        return response()->json(['message' => 'حُذفت القائمة.']);
    }

    private function assertPending(Lookup $lookup): void
    {
        if (! $lookup->isPending()) {
            throw ValidationException::withMessages([
                'lookup' => 'هذه القائمة ليست اقتراحاً معلَّقاً.',
            ]);
        }
    }

    /** مفتاحٌ فريدٌ داخل المجموعة — التسميات تتكرّر، والمفاتيح لا. */
    private function uniqueKey(string $group, string $label): string
    {
        $base = Lookup::keyFromLabel($label);
        $key = $base;
        $suffix = 2;

        while (Lookup::query()->where('group', $group)->where('key', $key)->exists()) {
            $key = $base.'-'.$suffix++;
        }

        return $key;
    }
}
