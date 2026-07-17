<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lookup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lookup
 */
final class LookupResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // `id` يلزم نماذجَ التحرير (اختيار المجال/المدينة)، و`key` يلزم
            // الفلترة العامّة. كلاهما غير معرِّف لشخص، فلا حرج في كشفهما.
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label_ar,

            // المدينة تعرف دولتها: الواجهة تُرشّح المدن بالدولة المختارة،
            // وتقرأ العملة من `meta` الدولة. لا «QAR» في كود الواجهة.
            'parent_id' => $this->parent_id,

            // ألوان الشارة تصل الواجهةَ من قاعدة البيانات لا من CSS.
            // هكذا تُضاف حالةٌ وظيفية رابعة بلونها دون سطر برمجي.
            'meta' => $this->meta,

            // اقتراحٌ من «أخرى» ينتظر مشرفاً. يراه صاحبه في ملفّه، ولا يظهر
            // في قائمة غيره — فالواجهة تُعلمه بأنّ قيمته قيد المراجعة.
            'status' => $this->status,
        ];
    }
}
