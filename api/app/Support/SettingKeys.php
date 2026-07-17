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

    /**
     * رسم الكشف الافتراضي.
     *
     * ★ ليس السعر النهائي: المجال قد يحمل سعراً خاصّاً في `lookups.meta.reveal_fee`.
     * هذا هو ما يُطبَّق حين لا يُسعَّر المجال. راجع RevealPricing.
     */
    public const REVEAL_FEE = 'reveal.fee';

    // ── الوظائف ──
    /**
     * ★ عدد الوظائف **المفتوحة** المجّانية لكل شركة.
     *
     * `open` وحدها تُحسَب: إغلاق وظيفةٍ يحرّر خانتها. فيُثاب من ينظّف لوحته،
     * ولا يمتلئ الموقع بوظائف ميتة تُغرق الباحثين عن عمل.
     */
    public const JOB_FREE_SLOTS = 'jobs.free_slots';

    /** ثمن الخانة الواحدة بعد استنفاد الحصّة. */
    public const JOB_EXTRA_SLOT_FEE = 'jobs.extra_slot_fee';

    public const FEATURE_JOBS = 'features.jobs';

    // ── مؤشّر الطلب ──
    /**
     * عتبتا شارة «مطلوب».
     *
     * ★ العدد نفسه لا يُرسَل للشركة أبداً — الشارة وحدها. إظهار «كشفته 5
     * شركات» يُخبر الشركةَ بنشاط منافسيها، ويُظهر من لم يُطلَب بعدُ بـ «0».
     */
    public const DEMAND_BADGE_MIN = 'demand.badge_min';

    public const DEMAND_BADGE_HIGH_MIN = 'demand.badge_high_min';

    // ── مؤشّر الانتقال: نضارة الإشارة ──
    /**
     * ★ بعد كم يومٍ نسأل الموظّف: «ما زلتَ كما أنت؟»
     *
     * ⚠️ العتبات التي تُحوّل النسبة إلى حالة **ليست هنا** — تعيش في
     *    `lookups.meta.readiness_min`، فتُضاف حالةٌ رابعة بعتبتها ولونها من
     *    لوحة القوائم بلا سطر كود. راجع ReadinessMapper.
     */
    public const READINESS_FRESH_DAYS = 'readiness.fresh_days';

    /**
     * ★★ متى تُطفأ الشارة لأنّها بارت.
     *
     * هذا هو الفرق بين حقلٍ للزينة وإشارةٍ تجارية. الشركة تدفع رسم الكشف على
     * أساس هذه الشارة؛ فإن كان عمرُها ثمانية أشهر فقد بعناها ذكرى لا خبراً —
     * وهي أوّل ما يُطالَب به استردادٌ، وآخر ما تُغفَر به ثقة.
     */
    public const READINESS_STALE_DAYS = 'readiness.stale_days';

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

    // ── الإشراف: بوّابتا السوق ──
    /**
     * ★ هل ينتظر ملفّ الكفاءة موافقةَ مشرف قبل أن يظهر للشركات؟
     *
     * `false` افتراضاً: الموظّف ينشر فوراً. الاحتكاك هنا يطرد المرشّحين، وهم
     * الطرف الشحيح في السوق. الآلة كلّها باقية — إشعالُها نقرةٌ في اللوحة إن
     * امتلأت المنصّة بملفّات وهمية.
     */
    public const MODERATION_REQUIRE_CANDIDATE_APPROVAL = 'moderation.require_candidate_approval';

    /**
     * ★★ هل تنتظر الشركة توثيقَ مشرف قبل أن تتصفّح الكفاءات؟
     *
     * `true` افتراضاً — وهو غير قابل للمساومة عملياً: من يتصفّح هو من يرى
     * ملفّات الناس. شركةٌ وهمية تتصفّح = حصادُ بياناتٍ بلا حسيب. والاحتكاك
     * هنا في محلّه: الشركة هي الطرف الذي **يدفع**، فيحتمل دقيقةَ انتظار.
     */
    public const MODERATION_REQUIRE_COMPANY_VERIFICATION = 'moderation.require_company_verification';

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
