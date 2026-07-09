<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالات الكشف.
 *
 * ⚠️ `Completed` وحدها تفكّ الحجب. لا Pending، ولا Refunded.
 * راجع Brain/06-masking-and-reveal.md §6.
 */
enum RevealStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /** هل تفكّ هذه الحالة الحجب؟ */
    public function unmasks(): bool
    {
        return $this === self::Completed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الدفع',
            self::Completed => 'مكتمل',
            self::Failed => 'فاشل',
            self::Refunded => 'مُسترَدّ',
        };
    }
}
