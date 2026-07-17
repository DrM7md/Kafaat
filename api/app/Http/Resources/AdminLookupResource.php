<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lookup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ما يراه المشرف من القائمة — أكثر ممّا يراه المستخدم.
 *
 * `LookupResource` العامّ لا يكشف من اقترح القيمة: هويّة الموظّف محجوبة
 * حتى الكشف، ولا يجوز أن يتسرّب اسمه من باب قائمةٍ مرجعية. هنا يظهر، لأن
 * المشرف يملك `lookup.manage` — وأثرُه مكتوب في التدقيق.
 *
 * @mixin Lookup
 */
final class AdminLookupResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'key' => $this->key,
            'label_ar' => $this->label_ar,
            'label_en' => $this->label_en,
            'parent_id' => $this->parent_id,
            'parent_label' => $this->parent?->label_ar,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'status' => $this->status,
            'meta' => $this->meta,
            'suggested_by' => $this->whenLoaded('suggestedBy', fn () => $this->suggestedBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
