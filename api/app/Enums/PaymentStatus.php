<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /**
     * ★ استقرّ المال. لا حدث webhook يُغيّر هذه الحالة تلقائياً.
     *
     * `failed` ليست منها عمداً: البوّابات تسمح بنجاح المحاولة نفسها بعد
     * فشلها (تحقّق 3‑D Secure مثلاً). إسقاط ذلك الحدث يعني مالاً دخل بلا
     * كشف — وهو أحد الضمانات الأربعة (Brain/06 §6).
     */
    public function isSettled(): bool
    {
        return $this === self::Paid || $this === self::Refunded;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Paid => 'مدفوع',
            self::Failed => 'فاشل',
            self::Refunded => 'مُسترَدّ',
        };
    }
}
