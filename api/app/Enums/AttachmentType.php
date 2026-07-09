<?php

declare(strict_types=1);

namespace App\Enums;

enum AttachmentType: string
{
    case Cv = 'cv';
    case Certificate = 'certificate';

    /**
     * شهادة حُسن السيرة والسلوك.
     *
     * ADR-013: يرفعها الموظّف بنفسه (تصريح ذاتي). المنصّة وسيط تخزين
     * لا جهة تحقّق، ولا تستعلم عن سجلٍّ جنائي — لا يجوز ذلك لطرف خاصّ
     * في قطر. الميزة خلف مفتاح features.good_conduct_upload، ومعطّلة
     * افتراضياً حتى مراجعة قانونية. راجع Brain/09-legal-compliance.md §2.
     */
    case GoodConduct = 'good_conduct';

    case Logo = 'logo';

    /** المرفقات المحجوبة حتى الكشف — تحوي اسم صاحبها حتماً. */
    public function isSensitive(): bool
    {
        return $this !== self::Logo;
    }
}
