<?php

declare(strict_types=1);

namespace App\Support;

/**
 * كتالوج مفاتيح الإعدادات — Brain/05-settings-engine.md §3.
 *
 * ⚠️ لا تكتب قيمةً ثابتة لقاعدة عملٍ مؤثّرة في الكود.
 * الاختبار: «لو طلب المالك تغيير هذا غداً، أأحتاج نشر كود؟» إن نعم — فهو إعداد.
 */
final class SettingKeys
{
    // ── billing ──
    public const BILLING_MODEL = 'billing.model';           // pay_per_reveal|subscription|hybrid

    public const BILLING_CURRENCY = 'billing.currency';

    public const BILLING_GATEWAY = 'billing.gateway';

    /**
     * ★ الدفع قيد التطوير — لم تُربَط بوّابةٌ حقيقية بعد.
     *
     * عامّ: تقرؤه الواجهة لتُعلن ذلك للمستخدم بدل أن تَعِده بدفعٍ لا يقع.
     * يُرفَع إلى `false` في اليوم الذي تُربَط فيه بوّابة حقيقية.
     */
    public const BILLING_UNDER_DEVELOPMENT = 'billing.under_development';

    public const REVEAL_FEE = 'reveal.fee';

    // ── masking ★ ──
    public const MASKING_FIELDS = 'masking.fields';

    public const MASKING_CURRENT_EMPLOYER_IN_EXPERIENCE = 'masking.mask_current_experience_employer';

    public const MASKING_SHOW_REDACTED_CV = 'masking.show_redacted_cv';

    // ── refund ──
    public const REFUND_ENABLED = 'refund.enabled';

    public const REFUND_WINDOW_DAYS = 'refund.window_days';

    public const REFUND_PERCENTAGE = 'refund.percentage';

    public const REFUND_TRIGGER = 'refund.trigger';

    public const REFUND_REBLOCKS_ACCESS = 'refund.reblocks_access';

    // ── privacy ──
    public const PRIVACY_EMPLOYER_BLIND = 'privacy.employer_blind';

    public const PRIVACY_NOTIFY_ON_REVEAL = 'privacy.notify_on_reveal';

    public const PRIVACY_PROFILE_VISIBILITY_DEFAULT = 'privacy.profile_visibility_default';

    // ── features ──
    public const FEATURE_SUBSCRIPTIONS = 'features.subscriptions';

    public const FEATURE_COMPANY_VERIFICATION = 'features.company_verification';

    public const FEATURE_NOTIFICATIONS = 'features.notifications';

    public const FEATURE_GOOD_CONDUCT_UPLOAD = 'features.good_conduct_upload';

    public const FEATURE_MATCHING_SCORE = 'features.matching_score';

    // ── platform ──
    public const PLATFORM_NAME = 'platform.name';

    public const PLATFORM_SUPPORT_EMAIL = 'platform.support_email';

    public const PLATFORM_TERMS_VERSION = 'platform.terms_version';

    public const PLATFORM_PRIVACY_VERSION = 'platform.privacy_version';

    /**
     * حقول لا يجوز رفع الحجب عنها دون تأكيد مزدوج صريح.
     * إزالتها من masking.fields تعني منح المنتج مجّاناً.
     *
     * @return list<string>
     */
    public static function protectedMaskedFields(): array
    {
        return ['name', 'phone', 'email'];
    }

    /**
     * ★ الحجب الافتراضي — يُستعمل حين يغيب إعداد `masking.fields`.
     *
     * لا يجوز أن يكون الافتراضي `[]`. مصفوفةٌ فارغة تعني «لا تحجب شيئاً»،
     * فيصير غيابُ صفٍّ في القاعدة — أو كاشٌ بائت — كشفاً كاملاً صامتاً
     * لكل اسمٍ وهاتفٍ وبريد. العطب يجب أن يُغلق الباب لا يفتحه.
     *
     * هذه أيضاً مصدر الحقيقة الذي يبذر به `SettingSeeder`.
     *
     * @return list<string>
     */
    public static function defaultMaskedFields(): array
    {
        return [
            'name', 'initials', 'avatar', 'phone', 'email',
            'current_employer', 'attachments', 'links',

            // البند الجزائي ومبلغه: شرطٌ في عقد الموظّف الحالي.
            // كشفهما قبل الدفع يمنح الشركة ورقة تفاوضٍ مجّاناً.
            'notice_period_clause', 'penalty_amount',
        ];
    }
}
