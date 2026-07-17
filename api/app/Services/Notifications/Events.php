<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Notifications\PlatformNotification;
use App\Notifications\ProfileRevealedNotification;

/**
 * كتالوج الإشعارات — نصُّ كل حدثٍ ووجهتُه في موضعٍ واحد.
 *
 * جمعُها هنا يجعل مراجعةَ «ماذا نقول للناس؟» قراءةَ ملفٍّ واحد، لا تتبّعاً
 * لعشرة متحكّمات. وهو أيضاً الموضع الذي يُفحَص فيه أنّ **لا اسم مرشّحٍ**
 * تسرّب إلى نصّ — راجع تحذير `PlatformNotification`.
 *
 * ما ليس هنا مقصود:
 *
 * ★ إشعارات المشرف تقتصر على **ما يستدعي قراراً منه**. سجلّ التدقيق
 *   (`/admin/audit`) هو السجلّ الكامل لكل ما يجري — وصبُّه كلِّه في
 *   الإشعارات يصنع خرطوماً لا يُقرأ، فيضيع فيه ما يحتاج فعلاً.
 */
final class Events
{
    // ── الموظّف ──

    /**
     * اسم الشركة مذكور: هي من بادر بالكشف، فلا سرّ لها تجاهه.
     *
     * صنفٌ خاصّ لا `PlatformNotification`: اختباراتٌ قائمة تحرسه بالاسم
     * («إشعارٌ واحد لا اثنان» عند إعادة تشغيل الـ webhook)، وشكلُ بياناته
     * موحَّدٌ مع البقيّة.
     */
    public static function profileRevealed(string $companyName): ProfileRevealedNotification
    {
        return new ProfileRevealedNotification($companyName);
    }

    public static function profileApproved(): PlatformNotification
    {
        return new PlatformNotification(
            key: 'profile.approved',
            title: 'اعتُمد ملفّك',
            body: 'صار ملفّك ظاهراً للشركات. هويّتك ما زالت محجوبة حتى تدفع شركةٌ رسم الكشف.',
            link: '/profile',
        );
    }

    public static function profileRejected(?string $reason): PlatformNotification
    {
        return new PlatformNotification(
            key: 'profile.rejected',
            title: 'يحتاج ملفّك تعديلاً',
            body: $reason ?? 'راجع ملفّك ثم قدّمه من جديد.',
            link: '/profile',
        );
    }

    // ── الشركة ──

    /**
     * ★ الإشعار الذي يجعل الكشف قابلاً للاستعمال أصلاً.
     *
     * الكشف **غير متزامن**: الشركة تدفع فتتلقّى 202، ويفكّ الحجبَ webhook
     * لاحقاً. بلا هذا الإشعار لا تعرف متى صار بوسعها رؤية بيانات التواصل،
     * فتظلّ تُحدّث الصفحة.
     *
     * ⚠️ `ref` لا اسم — راجع تحذير `PlatformNotification`.
     */
    public static function revealCompleted(string $ref): PlatformNotification
    {
        return new PlatformNotification(
            key: 'reveal.completed',
            title: 'اكتمل الكشف',
            body: 'صارت بيانات التواصل مع المرشّح متاحةً لك الآن.',
            link: "/candidates/{$ref}",
            meta: ['ref' => $ref],
        );
    }

    /** الاسترداد يُعيد الحجب — الصمت هنا يعني شركةً تظنّ أنّ لها وصولاً وقد زال. */
    public static function revealRefunded(string $ref): PlatformNotification
    {
        return new PlatformNotification(
            key: 'reveal.refunded',
            title: 'استُرِدّ مبلغ كشف',
            body: 'أُعيد المبلغ، وعاد الحجب على بيانات التواصل مع هذا المرشّح.',
            link: '/reveals',
            meta: ['ref' => $ref],
        );
    }

    /** ★ اللحظة التي يُفتح فيها الموقع للشركة. صمتُنا هنا يتركها تنتظر بلا خبر. */
    public static function companyVerified(): PlatformNotification
    {
        return new PlatformNotification(
            key: 'company.verified',
            title: 'وُثِّقت شركتك',
            body: 'صار بوسعك تصفّح الكفاءات وكشف من يناسبك.',
            link: '/candidates',
        );
    }

    /**
     * رُفض توثيق الشركة.
     *
     * لا نذكر سبباً هنا: نموذج التوثيق لا يجمعه بعد، واختلاقُ سببٍ عامّ أسوأ
     * من الصمت. الشركة تُحال إلى الدعم.
     */
    public static function companyRejected(): PlatformNotification
    {
        return new PlatformNotification(
            key: 'company.rejected',
            title: 'لم يُقبل توثيق شركتك',
            body: 'تعذّر توثيق حسابك. تواصل مع الدعم لمعرفة السبب واستكمال ما ينقص.',
            link: '/account',
        );
    }

    /**
     * ★★ مرشّحٌ أبدى إعجابه بوظيفة.
     *
     * ⚠️ **لا اسم هنا.** الإشعار صفٌّ دائم، والحجب حالةٌ متغيّرة — واسمٌ يُخزَّن
     *    هنا يتسرّب إلى الأبد. نُرسل `ref` الوظيفة، والشركة تفتح قائمة
     *    المعجبين فترى بطاقاتٍ محجوبة وأزرارَ كشف.
     *
     *    وهذا هو بيت القصيد: الإعجاب يبيع **النيّة** لا الاسم. الشركة تعرف
     *    أنّ مرشّحاً يريد وظيفتها — فتدفع رسم الكشف عن رغبةٍ لا مخاطرة.
     */
    public static function jobInterest(string $jobTitle, string $jobRef): PlatformNotification
    {
        return new PlatformNotification(
            key: 'job.interest',
            title: 'مرشّح مهتمّ بوظيفتك',
            body: "أبدى مرشّحٌ إعجابه بـ «{$jobTitle}». افتح الوظيفة لترى ملفّه — هويّته محجوبة حتى تكشفها.",
            link: "/jobs/{$jobRef}/interested",
            meta: ['job_ref' => $jobRef],
        );
    }

    // ── المشرف: ما يستدعي قراراً ──

    /** ⚠️ `ref` لا اسم: هويّة المرشّح محجوبة عن المشرف أيضاً حتى يستعمل صلاحية الإشراف. */
    public static function profileSubmitted(string $ref): PlatformNotification
    {
        return new PlatformNotification(
            key: 'admin.profile_submitted',
            title: 'كفاءة تنتظر الاعتماد',
            body: "قُدِّم ملفّ «{$ref}» للمراجعة.",
            link: '/admin/candidates',
            meta: ['ref' => $ref],
        );
    }

    public static function companyRegistered(string $legalName): PlatformNotification
    {
        return new PlatformNotification(
            key: 'admin.company_registered',
            title: 'شركة جديدة',
            body: "سجّلت «{$legalName}» وتنتظر التوثيق.",
            link: '/admin/companies',
            meta: ['company' => $legalName],
        );
    }

    /**
     * شركةٌ موثَّقة بدّلت رقم سجلّها التجاري.
     *
     * قرارٌ ينتظر المشرف فعلاً لا خبرٌ يُقرأ: الختم سقط تلقائياً (عاد التوثيق
     * إلى «معلّق»)، والشركة الآن بلا وصولٍ حتى يتحقّق من السجلّ الجديد.
     */
    public static function companyReverificationNeeded(string $legalName): PlatformNotification
    {
        return new PlatformNotification(
            key: 'admin.company_reverify',
            title: 'شركة بدّلت سجلّها التجاري',
            body: "غيّرت «{$legalName}» رقم سجلّها، فسقط توثيقها. تتحقّق منه وتوثّقها من جديد.",
            link: '/admin/companies',
            meta: ['company' => $legalName],
        );
    }

    public static function lookupSuggested(string $label, string $group): PlatformNotification
    {
        return new PlatformNotification(
            key: 'admin.lookup_suggested',
            title: 'قيمة مقترحة تنتظر قرارك',
            body: "اقترح موظّفٌ «{$label}». لا يراها غيره حتى تعتمدها.",
            link: '/admin/lookups',
            meta: ['label' => $label, 'group' => $group],
        );
    }

    /**
     * ★★ أخطر إشعارٍ في المنصّة.
     *
     * مالٌ دخل ولم يُمكن ربطه بكشف. يحتاج تدخّلاً بشرياً — ولا يجوز أن
     * يُسقَط بصمت في سجلٍّ لا يقرؤه أحد.
     */
    public static function reconciliationRequired(string $providerRef): PlatformNotification
    {
        return new PlatformNotification(
            key: 'admin.reconciliation_required',
            title: '⚠️ دفعةٌ لم تُربَط بكشف',
            body: "وصل مالٌ بمرجع «{$providerRef}» ولم يُعثر على كشفٍ يقابله. يحتاج مراجعةً يدوية.",
            link: '/admin/audit',
            meta: ['provider_ref' => $providerRef],
        );
    }
}
