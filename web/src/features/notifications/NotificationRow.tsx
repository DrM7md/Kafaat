import type { AppNotification, NotificationKey } from './queries'

/**
 * صفّ الإشعار — تشترك فيه قائمةُ الجرس وصفحةُ الإشعارات.
 *
 * `compact` لقائمة الجرس: صفٌّ مسطّح داخل لوحةٍ ضيّقة. البطاقة المرتفعة
 * بظلّها وقفزتها تصلح لصفحةٍ واسعة، وتزدحم داخل قائمةٍ منسدلة.
 */
export function NotificationRow({
  notification,
  onOpen,
  compact = false,
}: {
  notification: AppNotification
  onOpen: () => void
  compact?: boolean
}) {
  const unread = notification.read_at === null
  const tone = TONES[notification.key as NotificationKey] ?? TONES.default

  const shape = compact
    ? `rounded-xl p-3 hover:bg-surface-muted ${unread ? 'bg-mint/40' : ''}`
    : `rounded-[16px] border p-4 transition-[transform,box-shadow] duration-200 hover:-translate-y-0.5 hover:shadow-[var(--k-shadow-hover)] ${
        unread ? 'border-[var(--k-border-strong)] bg-surface' : 'border-[var(--k-border)] bg-surface-alt'
      }`

  return (
    <button
      type="button"
      onClick={onOpen}
      className={`flex w-full items-start gap-3.5 text-right ${shape} ${
        notification.link !== null ? 'cursor-pointer' : 'cursor-default'
      }`}
    >
      <span
        className="mt-0.5 grid size-9 shrink-0 place-items-center rounded-[11px] text-[15px]"
        style={{ background: tone.bg, color: tone.fg }}
        aria-hidden
      >
        {tone.icon}
      </span>

      <span className="min-w-0 flex-1">
        <span className="flex items-center gap-2">
          <span
            className={`text-[13.5px] ${
              unread ? 'font-semibold text-ink' : 'font-medium text-[var(--k-text-secondary)]'
            }`}
          >
            {notification.title}
          </span>
          {unread && <span className="size-1.5 shrink-0 rounded-full bg-primary" />}
        </span>

        <span className="mt-1 block text-[12.5px] leading-6 text-[var(--k-text-secondary)]">
          {notification.body}
        </span>

        <span className="numeric mt-1.5 block text-[11px] text-[var(--k-text-muted)]">
          {relative(notification.created_at)}
        </span>
      </span>
    </button>
  )
}

/**
 * أيقونة ولون لكل نوع.
 *
 * ⚠️ الأحمر للتسوية المالية وحدها — مالٌ دخل ولم يُربط بكشف. استعمالُه في
 *    إشعارٍ روتيني يُفقده معناه، فيُتجاهَل يوم يهمّ.
 */
const TONES: Record<string, { icon: string; bg: string; fg: string }> = {
  'profile.revealed': { icon: '👁', bg: 'var(--k-gold-tint)', fg: '#96682A' },
  'profile.approved': { icon: '✓', bg: '#E7F3EA', fg: '#1E7A44' },
  'profile.rejected': { icon: '!', bg: 'rgb(200 60 60 / 0.08)', fg: '#8a2b2b' },
  'reveal.completed': { icon: '🔓', bg: 'var(--k-mint)', fg: 'var(--k-primary)' },
  'reveal.refunded': { icon: '↩', bg: 'var(--k-surface-muted)', fg: 'var(--k-text-secondary)' },
  'company.verified': { icon: '✓', bg: '#E7F3EA', fg: '#1E7A44' },
  'admin.profile_submitted': { icon: '📋', bg: 'var(--k-mint)', fg: 'var(--k-primary)' },
  'admin.company_registered': { icon: '🏢', bg: 'var(--k-mint)', fg: 'var(--k-primary)' },
  'admin.company_reverify': { icon: '🔁', bg: 'var(--k-gold-tint)', fg: '#96682A' },
  'admin.lookup_suggested': { icon: '＋', bg: 'var(--k-surface-muted)', fg: 'var(--k-text-secondary)' },
  'admin.reconciliation_required': { icon: '⚠', bg: 'rgb(200 60 60 / 0.1)', fg: '#8a2b2b' },
  default: { icon: '•', bg: 'var(--k-surface-muted)', fg: 'var(--k-text-secondary)' },
}

function relative(iso: string | null): string {
  if (iso === null) return ''

  const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60_000)

  if (minutes < 1) return 'الآن'
  if (minutes < 60) return `منذ ${minutes} دقيقة`

  const hours = Math.round(minutes / 60)
  if (hours < 24) return `منذ ${hours} ساعة`

  const days = Math.round(hours / 24)
  if (days === 1) return 'منذ يوم'
  if (days <= 10) return `منذ ${days} أيام`

  return `منذ ${days} يوماً`
}
