import type { Candidate } from '@/shared/api/types'

/**
 * مؤشّر الطلب — شارةٌ لا رقم.
 *
 * الخادم لا يرسل عدد الكشوف أصلاً (راجع `MaskedProfileResource::demandTier`):
 * «كشفته 5 شركات» يُخبر الشركةَ بنشاط منافسيها، ويصِم من لم يُطلَب بعدُ بـ «0».
 * فتصل مرتبةٌ فقط، ومن دون العتبة لا شارة له.
 *
 * ⚠️ اللون بترولي لا ذهبي. الذهبي محجوز لفعل الكشف ولشارة «كفاءة مميّزة»
 *    (Brain/02 §2) — واستعماله هنا يُفقده معناه.
 */
export function DemandBadge({ demand }: { demand: Candidate['demand'] }) {
  if (demand === undefined || demand === null) return null

  const isHigh = demand === 'high'

  return (
    <span
      className="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[10px] font-medium"
      style={
        isHigh
          ? { color: '#fff', background: 'var(--k-primary)', borderColor: 'var(--k-primary)' }
          : { color: 'var(--k-primary)', background: 'var(--k-mint)', borderColor: 'rgb(15 80 73 / 0.2)' }
      }
      title={isHigh ? 'كشفت هويّته شركاتٌ كثيرة' : 'يتزايد الطلب عليه'}
    >
      <TrendIcon />
      {isHigh ? 'مطلوب بشدّة' : 'مطلوب'}
    </span>
  )
}

function TrendIcon() {
  return (
    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" aria-hidden>
      <path d="M3 17 9 11l4 4 8-8" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M15 3h6v6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}

/** «مسجّل منذ 120 يوماً» — من تاريخ ظهوره للشركات لا من إنشاء حسابه. */
export function ListedSince({ days }: { days: number | null | undefined }) {
  if (days === undefined || days === null) return null

  return <span className="numeric text-[11px] text-[var(--k-text-muted)]">{label(days)}</span>
}

function label(days: number): string {
  if (days <= 0) return 'مُدرَج اليوم'
  if (days === 1) return 'مُدرَج منذ يوم'
  if (days === 2) return 'مُدرَج منذ يومين'
  if (days <= 10) return `مُدرَج منذ ${days} أيام`

  return `مُدرَج منذ ${days} يوماً`
}
