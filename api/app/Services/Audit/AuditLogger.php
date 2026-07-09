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

    // ── الإشراف ──
    /** مشرف رأى ملفّاً مكشوفاً بصلاحية الإشراف — أخطر فعل غير مالي. */
    public const PROFILE_VIEWED_AS_MODERATOR = 'profile.viewed_as_moderator';

    // ── الصلاحيات ──
    public const ROLE_PERMISSIONS_UPDATED = 'role.permissions_updated';

    public const ROLE_ASSIGNED = 'role.assigned';

    public const ROLE_REVOKED = 'role.revoked';

    public const PERMISSION_GRANTED_DIRECT = 'permission.granted_direct';

    public const PERMISSION_REVOKED_DIRECT = 'permission.revoked_direct';

    // ── الإعدادات والامتثال ──
    public const SETTINGS_UPDATED = 'settings.updated';

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
