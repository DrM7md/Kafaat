<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * سجلّ التدقيق — إلحاقيّ فقط (ADR-011).
 *
 * سجلٌّ قابل للتعديل ليس سجلّ تدقيق. الحارسان أدناه يفرضان ذلك في الكود،
 * ولا يكفيان: صلاحيات مستخدم قاعدة البيانات في الإنتاج يجب أن تمنع
 * UPDATE و DELETE على هذا الجدول أيضاً.
 *
 * @property string $action
 * @property array<string, mixed>|null $meta
 */
#[Fillable(['actor_id', 'action', 'subject_type', 'subject_id', 'ip', 'user_agent', 'meta'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('سجلّ التدقيق إلحاقيّ: لا يُعدَّل.');
        });

        static::deleting(function (): never {
            throw new LogicException('سجلّ التدقيق إلحاقيّ: لا يُحذَف.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
