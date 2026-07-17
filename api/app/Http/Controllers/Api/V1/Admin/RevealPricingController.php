<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\ProfileVisibility;
use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Services\Audit\AuditLogger;
use App\Services\Reveal\RevealPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * تسعير الكشف لكل مجال.
 *
 * المشرف يرى **كل** المجالات — المسعَّرة وغير المسعَّرة — ومعها عددُ المرشّحين
 * في كلٍّ منها، فيُسعّر عن بيّنة لا عن حدس.
 *
 * ⚠️ التسعير هنا لا يمسّ فاتورةً قائمة: السعر يُجمَّد في `payments.amount`
 *    لحظة إنشائها (ADR-006). رفعُ سعر مجالٍ اليوم لا يُعيد تسعير كشفِ أمس.
 */
final class RevealPricingController extends Controller
{
    public function __construct(
        private readonly RevealPricing $pricing,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): JsonResponse
    {
        // عدد المرشّحين المعروضين في كل مجال — استعلامٌ واحد لا استعلامٌ لكل مجال.
        $counts = EmployeeProfile::query()
            ->where('visibility', ProfileVisibility::Public)
            ->whereNotNull('published_at')
            ->where('approval_status', ApprovalStatus::Approved)
            ->whereNotNull('field_id')
            ->groupBy('field_id')
            ->select('field_id', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'field_id');

        $fields = Lookup::query()
            ->approved()
            ->where('group', Lookup::FIELD)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Lookup $field): array => [
                'id' => $field->id,
                'label' => $field->label_ar,

                // السعر الخاصّ إن وُجد — و`null` تعني «يرث الافتراضي»،
                // وهو تمييزٌ يلزم: سعرٌ صفر ليس «بلا سعر».
                'fee' => $this->explicitFee($field),

                // ما يُخصَم فعلاً اليوم من شركةٍ تكشف مرشّحاً في هذا المجال.
                'effective_fee' => $this->pricing->forField($field),
                'candidates' => (int) ($counts[$field->id] ?? 0),
            ]);

        return response()->json([
            'fields' => $fields,
            'default_fee' => $this->pricing->default(),
        ]);
    }

    public function update(Request $request, Lookup $field): JsonResponse
    {
        abort_unless($field->group === Lookup::FIELD, 404);

        $data = $request->validate([
            // `null` تُرجع المجال إلى السعر الافتراضي. و`0` سعرٌ مشروع:
            // كشفٌ مجّاني لمجالٍ تريد تنشيطه.
            'fee' => ['present', 'nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $before = $this->explicitFee($field);
        $after = $data['fee'] === null ? null : (int) $data['fee'];

        $meta = $field->meta ?? [];

        if ($after === null) {
            unset($meta[RevealPricing::META_KEY]);
        } else {
            $meta[RevealPricing::META_KEY] = $after;
        }

        // `meta` تحمل ألوان الشارة أيضاً — نُحدّث المفتاح ولا نطمس الكائن.
        $field->forceFill(['meta' => $meta === [] ? null : $meta])->save();

        $this->audit->log(AuditLogger::REVEAL_FEE_UPDATED, $field, [
            'field' => $field->label_ar,
            'before' => $before,
            'after' => $after,
        ]);

        return response()->json([
            'field' => [
                'id' => $field->id,
                'label' => $field->label_ar,
                'fee' => $after,
                'effective_fee' => $this->pricing->forField($field->fresh()),
            ],
        ]);
    }

    /** السعر المكتوب صراحةً لهذا المجال — لا الموروث. */
    private function explicitFee(Lookup $field): ?int
    {
        $fee = $field->meta[RevealPricing::META_KEY] ?? null;

        return is_numeric($fee) ? (int) $fee : null;
    }
}
