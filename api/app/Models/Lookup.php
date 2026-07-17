<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * قائمة مرجعية قابلة للتخصيص: الحالات الوظيفية، المجالات، المدن، الدول…
 *
 * `meta` تحمل ألوان الشارة. وضعُها هنا لا في CSS هو ما يجعل «إضافة حالة
 * وظيفية رابعة بلونها» عملاً إدارياً لا برمجياً. Brain/05-settings-engine.md §4.
 *
 * وللسبب نفسه **الدولة قائمة لا ثابت**: عملتها في `meta.currency`. إضافة
 * الإمارات بدرهمها عملٌ إداري، ولا سطر كود يذكر «قطر» أو «QAR».
 *
 * @property int $id
 * @property string $group
 * @property string $key
 * @property int|null $parent_id
 * @property string $label_ar
 * @property string|null $label_en
 * @property array<string, mixed>|null $meta
 * @property int $sort_order
 * @property bool $is_active
 * @property string $status
 * @property int|null $suggested_by_user_id
 */
#[Fillable(['group', 'key', 'parent_id', 'label_ar', 'label_en', 'meta', 'sort_order', 'is_active', 'status', 'suggested_by_user_id'])]
class Lookup extends Model
{
    public const EMPLOYMENT_STATUS = 'employment_status';

    public const FIELD = 'field';

    public const CITY = 'city';

    public const COUNTRY = 'country';

    /**
     * الجنسية — غير `COUNTRY`.
     *
     * `COUNTRY` أين يعمل الموظّف (منها العملة والمدن)، و`NATIONALITY` من هو.
     * مجموعتان لأن قوائمهما تختلفان: نعمل في ستّ دولٍ خليجية، ويعمل عندنا
     * أهلُ الأرض كلّها.
     */
    public const NATIONALITY = 'nationality';

    public const INDUSTRY = 'industry';

    public const COMPANY_SIZE = 'company_size';

    /** قيمةٌ اقترحها موظّف عبر «أخرى» — يراها صاحبها وحده حتى يعتمدها مشرف. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

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

    /** الدولة التي تتبعها المدينة. */
    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** الموظّف الذي اقترح القيمة عبر «أخرى». لا يُكشف إلّا لمن يملك lookup.manage. */
    /** @return BelongsTo<User, $this> */
    public function suggestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by_user_id');
    }

    /** @param Builder<$this> $query */
    public function scopeGroup(Builder $query, string $group): void
    {
        $query->where('group', $group)->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * ما يُعرض للجميع: المعتمَد النشط وحده.
     *
     * الاقتراح المعلَّق **ليس** هنا — وإلّا صار خيار «أخرى» باباً خلفياً
     * يحقن به أيُّ موظّف قيمةً في قائمة الجميع بلا مراجعة.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('is_active', true)->where('status', self::STATUS_APPROVED);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * مفتاحٌ مستقرّ من تسمية عربية.
     *
     * `Str::slug` يُفرغ العربية تماماً (لا محارف لاتينية فيها)، فيعود بنصٍّ
     * فارغ — ولو اعتمدناه لتصادمت كل الاقتراحات العربية على مفتاحٍ واحد.
     * لذا نُطبّع التسمية نفسها ونُلحق بصمةً قصيرة تضمن التفرّد.
     */
    public static function keyFromLabel(string $label): string
    {
        $normalized = Str::squish($label);
        $slug = Str::slug($normalized);

        return $slug !== ''
            ? Str::limit($slug, 48, '')
            : 'x-'.substr(hash('sha256', $normalized), 0, 12);
    }
}
