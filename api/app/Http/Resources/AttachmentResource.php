<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ⚠️ لا يُرسَل `path` ولا `disk` ولا رابط تخزينٍ مباشر — أبداً.
 * الوصول حصراً عبر GET /attachments/{uuid} المحروس بـ AttachmentPolicy.
 *
 * @mixin Attachment
 */
final class AttachmentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'url' => route('attachments.show', ['attachment' => $this->uuid]),
        ];
    }
}
