<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة الوظيفة.
 *
 * ★ `Open` وحدها تُحسَب من الحصّة، وهي وحدها يراها الموظّفون. إغلاقُ وظيفةٍ
 *   يحرّر خانتها — فيُثاب من ينظّف لوحته، ولا يمتلئ الموقع بوظائف ميتة.
 */
enum JobStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';

    /** هل تشغل خانةً من حصّة الشركة؟ */
    public function occupiesSlot(): bool
    {
        return $this === self::Open;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودّة',
            self::Open => 'مفتوحة',
            self::Closed => 'مغلقة',
        };
    }
}
