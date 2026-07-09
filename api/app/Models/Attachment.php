<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttachmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * ⚠️ الوصول إلى المرفق حصراً عبر GET /attachments/{uuid} المحروس بـ
 * AttachmentPolicy. لا رابط مباشر إلى التخزين — رابطٌ واحد يتجاوز كل الحجب.
 *
 * @property string $uuid
 * @property AttachmentType $type
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 */
#[Fillable(['type', 'disk', 'path', 'original_name', 'mime', 'size', 'is_private'])]
class Attachment extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $attachment) {
            $attachment->uuid ??= (string) Str::uuid7();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttachmentType::class,
            'is_private' => 'boolean',
            'size' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
