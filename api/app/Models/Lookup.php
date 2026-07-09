<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * قائمة مرجعية قابلة للتخصيص: الحالات الوظيفية، المجالات، المدن…
 *
 * `meta` تحمل ألوان الشارة. وضعُها هنا لا في CSS هو ما يجعل «إضافة حالة
 * وظيفية رابعة بلونها» عملاً إدارياً لا برمجياً. Brain/05-settings-engine.md §4.
 *
 * @property string $group
 * @property string $key
 * @property string $label_ar
 * @property string|null $label_en
 * @property array<string, mixed>|null $meta
 */
#[Fillable(['group', 'key', 'label_ar', 'label_en', 'meta', 'sort_order', 'is_active'])]
class Lookup extends Model
{
    public const EMPLOYMENT_STATUS = 'employment_status';

    public const FIELD = 'field';

    public const CITY = 'city';

    public const INDUSTRY = 'industry';

    public const COMPANY_SIZE = 'company_size';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<$this> $query */
    public function scopeGroup(Builder $query, string $group): void
    {
        $query->where('group', $group)->where('is_active', true)->orderBy('sort_order');
    }
}
