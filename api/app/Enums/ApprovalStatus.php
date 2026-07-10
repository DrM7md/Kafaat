<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة اعتماد الملفّ المهني.
 *
 * ★ `Approved` وحدها تجعل الملفّ قابلاً للتصفّح. النشر رغبةُ الموظّف،
 *   والاعتماد قرارُ المنصّة — واجتماعهما شرطُ الظهور.
 */
enum ApprovalStatus: string
{
    /** أُنشئ ولم يُقدَّم بعد. */
    case Draft = 'draft';

    /** قُدِّم الطلب، بانتظار مراجعة المشرف. */
    case Pending = 'pending';

    case Approved = 'approved';

    case Rejected = 'rejected';

    public function isBrowsable(): bool
    {
        return $this === self::Approved;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودّة',
            self::Pending => 'بانتظار الاعتماد',
            self::Approved => 'معتمَد',
            self::Rejected => 'مرفوض',
        };
    }
}
