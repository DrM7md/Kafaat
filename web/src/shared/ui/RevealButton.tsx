import { CheckIcon, LockIcon } from './MaskedAvatar'
import { formatQar } from '@/shared/lib/format'

/**
 * زرّ الكشف — العنصر المحوري في المنتج.
 *
 * ★ انضباط اللون: الذهبي `#B4863B` يعني «هنا يقع الفعل الذي يُدرّ المال».
 *   وما إن يُستهلك الفعل، يهجر الذهبيُّ الزرَّ إلى البترولي. سلوكٌ مقصود
 *   في التصميم (`btnBg: revealed ? '#E4EDEB' : '#B4863B'`) — احترمه.
 *
 * قيمة الرسم تأتي من الإعدادات لا من الكود.
 */
export function RevealButton({
  revealed,
  fee,
  pending = false,
  compact = false,
  onReveal,
}: {
  revealed: boolean
  fee: number
  pending?: boolean
  compact?: boolean
  onReveal: () => void
}) {
  const label = revealed
    ? compact
      ? 'عرض'
      : 'عرض بيانات التواصل'
    : compact
      ? `كشف · ${formatQar(fee, false)}`
      : `كشف الهوية · ${formatQar(fee)}`

  return (
    <button
      type="button"
      onClick={onReveal}
      disabled={pending}
      className="group relative inline-flex cursor-pointer items-center gap-2 overflow-hidden rounded-[12px] px-4 py-2.5 text-[12.5px] font-semibold transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-[1.04] active:translate-y-0 disabled:cursor-wait disabled:opacity-70"
      style={
        revealed
          ? { background: 'var(--k-mint)', color: 'var(--k-primary-dark)' }
          : {
              background: 'linear-gradient(135deg, var(--k-gold-light), var(--k-gold))',
              color: '#fff',
              boxShadow: 'var(--k-shadow-gold)',
            }
      }
    >
      {/* لمعةٌ تمرّ عرضَ الزرّ عند التأشير — قبل الكشف وحده (الذهبيّ يلمع، ثمّ
          يهجر الزرَّ إلى النعناعي بعد الكشف فلا لمعةَ للمكتمل). */}
      {!revealed && (
        <span
          aria-hidden
          className="pointer-events-none absolute inset-0 -translate-x-[130%] bg-gradient-to-r from-transparent via-white/35 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-[130%]"
        />
      )}
      <span className="relative inline-flex">{revealed ? <CheckIcon /> : <LockIcon />}</span>
      <span className="relative">{pending ? 'جارٍ…' : label}</span>
    </button>
  )
}
