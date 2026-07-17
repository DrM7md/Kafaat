<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * سجلّ التدقيق — Brain/04-rbac.md §6، ADR-011.
 *
 * كل فعل حسّاس يمرّ من هنا: الكشف، الدفع، الاسترداد، تعديل الإعدادات،
 * تغيير الأدوار، استخدام صلاحية الإشراف، تصدير البيانات.
 */
final class AuditLogger
{
    // ── الكشف والمال ──
    public const CANDIDATE_REVEALED = 'candidate.revealed';

    public const CANDIDATE_REVEAL_GRANTED = 'candidate.reveal_granted';

    public const PAYMENT_PAID = 'payment.paid';

    public const PAYMENT_REFUNDED = 'payment.refunded';

    /** مالٌ دخل ولم يُمكن ربطه بكشف. يحتاج تدخّلاً بشرياً — لا يُسقَط بصمت. */
    public const PAYMENT_RECONCILIATION_REQUIRED = 'payment.reconciliation_required';

    /** تغيير سعر كشف مجال. فعلٌ ماليّ — يُدقَّق بالقيمة قبل وبعد. */
    public const REVEAL_FEE_UPDATED = 'reveal.fee_updated';

    // ── الوظائف ──
    public const JOB_OPENED = 'job.opened';

    public const JOB_CLOSED = 'job.closed';

    /** خانة وظيفة إضافية دُفع ثمنها. فعلٌ ماليّ. */
    public const JOB_SLOT_PURCHASED = 'job.slot_purchased';

    /** 🔴 مشرفٌ منح خانات مجّانية — إسقاطُ رسمٍ مستحقّ. */
    public const JOB_SLOTS_GRANTED = 'job.slots_granted';

    // ── الإشراف ──
    /** مشرف رأى ملفّاً مكشوفاً بصلاحية الإشراف — أخطر فعل غير مالي. */
    public const PROFILE_VIEWED_AS_MODERATOR = 'profile.viewed_as_moderator';

    /** مشرف تصفّح قائمة ملفّات مكشوفة. صفٌّ واحد للطلب، لا صفٌّ لكل مرشّح. */
    public const PROFILE_LISTED_AS_MODERATOR = 'profile.listed_as_moderator';

    // ── الصلاحيات ──
    public const ROLE_PERMISSIONS_UPDATED = 'role.permissions_updated';

    public const ROLE_ASSIGNED = 'role.assigned';

    public const ROLE_REVOKED = 'role.revoked';

    public const PERMISSION_GRANTED_DIRECT = 'permission.granted_direct';

    public const PERMISSION_REVOKED_DIRECT = 'permission.revoked_direct';

    // ── الإعدادات والامتثال ──
    public const SETTINGS_UPDATED = 'settings.updated';

    // ── القوائم المرجعية ──
    // القائمة تُغيّر ما يراه كل مستخدم في كل نموذج، فتعديلها فعلٌ إداريّ
    // بوزن تعديل إعداد — يُدقَّق بالقيمة قبل وبعد.
    public const LOOKUP_CREATED = 'lookup.created';

    public const LOOKUP_UPDATED = 'lookup.updated';

    public const LOOKUP_DELETED = 'lookup.deleted';

    /** اقتراحٌ من «أخرى» صار خياراً رسمياً يراه الجميع. */
    public const LOOKUP_SUGGESTION_APPROVED = 'lookup.suggestion_approved';

    public const LOOKUP_SUGGESTION_REJECTED = 'lookup.suggestion_rejected';

    public const DATA_EXPORTED = 'user.data_exported';

    public const ACCOUNT_ANONYMIZED = 'user.anonymized';

    public const CONSENT_GRANTED = 'consent.granted';

    public const CONSENT_REVOKED = 'consent.revoked';

    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(string $action, ?Model $subject = null, array $meta = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
