<?php

declare(strict_types=1);

namespace App\Services\Reveal;

use RuntimeException;

/**
 * الكشف ليس في حالةٍ تقبل الاسترداد: `completed` وحدها تُستردّ.
 *
 * تُرمى أيضاً حين تكون الدفعة قد استُرِدّت من قبل — فتمنع استرداداً
 * مزدوجاً من البوّابة: مالٌ يخرج مرّتين مقابل فاتورةٍ واحدة.
 */
final class RefundNotAllowedException extends RuntimeException
{
    public function __construct(public readonly int $revealId)
    {
        parent::__construct("الكشف #{$revealId} ليس في حالةٍ تقبل الاسترداد.");
    }
}
