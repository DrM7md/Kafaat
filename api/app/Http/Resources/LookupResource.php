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
            'key' => $this->key,
            'label' => $this->label_ar,

            // ألوان الشارة تصل الواجهةَ من قاعدة البيانات لا من CSS.
            // هكذا تُضاف حالةٌ وظيفية رابعة بلونها دون سطر برمجي.
            'meta' => $this->meta,
        ];
    }
}
