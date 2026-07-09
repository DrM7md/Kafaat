<?php

declare(strict_types=1);

namespace App\Support;

/**
 * كتالوج الصلاحيات — المصدر الوحيد للحقيقة.
 *
 * المرجع: Brain/04-rbac.md §3.
 * كل فحص وصول في المشروع يسأل عن صلاحية من هنا، لا عن دور.
 * الأدوار تُنشأ وتُعدَّل من قاعدة البيانات، والبذور أدناه نقطة انطلاق لا قيد.
 */
final class Permissions
{
    // ── الملفّات ──
    public const PROFILE_VIEW = 'profile.view';

    public const PROFILE_CREATE = 'profile.create';

    public const PROFILE_UPDATE = 'profile.update';

    /** 🔴 حرجة: من يملكها يرى كل الأسماء وبيانات التواصل مجّاناً. */
    public const PROFILE_MODERATE = 'profile.moderate';

    public const PROFILE_PUBLISH = 'profile.publish';

    // ── الكشف ──
    public const CANDIDATE_BROWSE = 'candidate.browse';

    public const CANDIDATE_REVEAL = 'candidate.reveal';

    public const CANDIDATE_CONTACT = 'candidate.contact';

    /** 🔴 حرجة: منح كشفٍ مجّاني دون دفع. */
    public const CANDIDATE_GRANT_REVEAL = 'candidate.grant_reveal';

    // ── الشركات ──
    public const COMPANY_VIEW = 'company.view';

    public const COMPANY_APPROVE = 'company.approve';

    public const COMPANY_SUSPEND = 'company.suspend';

    // ── المدفوعات ──
    public const PAYMENT_VIEW = 'payment.view';

    /** 🔴 حرجة: تحريك مال. */
    public const PAYMENT_REFUND = 'payment.refund';

    public const SUBSCRIPTION_MANAGE = 'subscription.manage';

    // ── الإعدادات ──
    public const SETTINGS_VIEW = 'settings.view';

    /** 🔴 حرجة: تعديل قواعد العمل كلّها. */
    public const SETTINGS_UPDATE = 'settings.update';

    public const LOOKUP_MANAGE = 'lookup.manage';

    /** 🔴 حرجة: التحكّم بمن يملك ماذا. */
    public const RBAC_MANAGE = 'rbac.manage';

    // ── النظام ──
    public const AUDIT_VIEW = 'audit.view';

    public const USER_MANAGE = 'user.manage';

    public const REPORT_EXPORT = 'report.export';

    /**
     * الصلاحيات مجمّعة — تُستهلك في البذور وفي لوحة المصفوفة.
     *
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        return [
            'الملفّات' => [
                self::PROFILE_VIEW,
                self::PROFILE_CREATE,
                self::PROFILE_UPDATE,
                self::PROFILE_MODERATE,
                self::PROFILE_PUBLISH,
            ],
            'الكشف' => [
                self::CANDIDATE_BROWSE,
                self::CANDIDATE_REVEAL,
                self::CANDIDATE_CONTACT,
                self::CANDIDATE_GRANT_REVEAL,
            ],
            'الشركات' => [
                self::COMPANY_VIEW,
                self::COMPANY_APPROVE,
                self::COMPANY_SUSPEND,
            ],
            'المدفوعات' => [
                self::PAYMENT_VIEW,
                self::PAYMENT_REFUND,
                self::SUBSCRIPTION_MANAGE,
            ],
            'الإعدادات' => [
                self::SETTINGS_VIEW,
                self::SETTINGS_UPDATE,
                self::LOOKUP_MANAGE,
                self::RBAC_MANAGE,
            ],
            'النظام' => [
                self::AUDIT_VIEW,
                self::USER_MANAGE,
                self::REPORT_EXPORT,
            ],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_merge(...array_values(self::grouped()));
    }

    /**
     * الصلاحيات الحرجة — كل ممارسة لها تُسجَّل في audit_logs،
     * وتُحاط بتحذير أحمر في لوحة المصفوفة.
     *
     * @return list<string>
     */
    public static function critical(): array
    {
        return [
            self::PROFILE_MODERATE,
            self::CANDIDATE_GRANT_REVEAL,
            self::PAYMENT_REFUND,
            self::SETTINGS_UPDATE,
            self::RBAC_MANAGE,
        ];
    }
}
