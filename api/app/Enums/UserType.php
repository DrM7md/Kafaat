<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نوع الحساب — بنيةٌ لا دور.
 *
 * ADR-007: `type` يحدّد أي جدول ملفٍّ يرتبط بالمستخدم فقط.
 * ممنوع منعاً باتّاً استعماله في قرارات الصلاحيات:
 *   ❌ if ($user->type === UserType::Admin)
 *   ✅ if ($user->can(Permissions::SETTINGS_UPDATE))
 */
enum UserType: string
{
    case Employee = 'employee';
    case Company = 'company';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'موظّف',
            self::Company => 'شركة',
            self::Admin => 'مشرف',
        };
    }
}
