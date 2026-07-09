<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * أنواع الموافقات — قانون حماية البيانات القطري رقم 13.
 *
 * `SensitiveData` منفصلة عمداً عن `Terms`: الموافقة على معالجة البيانات
 * ذات الطبيعة الخاصّة يجب أن تكون صريحة ومستقلّة، لا خانةً واحدة تبتلع
 * الاثنين. راجع Brain/09-legal-compliance.md §1.
 */
enum ConsentType: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';
    case SensitiveData = 'sensitive_data';
    case Marketing = 'marketing';
}
