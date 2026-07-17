import type { Lookup } from '@/shared/api/types'
import { formatStatusAge } from '@/shared/lib/readiness'

/**
 * شارة الحالة الوظيفية.
 *
 * الألوان تأتي من `lookups.meta` عبر الـ API لا من CSS — وهذا ما يجعل
 * «إضافة حالة رابعة بلونها» عملاً إدارياً لا برمجياً.
 * Brain/05-settings-engine.md §4.
 */
export function StatusBadge({ status, stale = false }: { status?: Lookup; stale?: boolean }) {
  if (status === undefined) return null

  // احتياطٌ لكل لون على حدة: `meta` قد تصل بحقولٍ ناقصة (حالةٌ أضافها مشرف
  // ونسي لونها)، فيبقى الشكل سليماً بدل أن يُرسَم بلا خلفية.
  const meta = status.meta

  // ★ الشارة البائتة تُطفأ ولا تُلوَّن: حالةٌ لم يُصدِّقها صاحبُها منذ ثلاثة
  //   أشهر ليست خبراً — هي ذكرى. وعرضُها بلونها كأنّها اليوم بيعُ بياناتٍ
  //   ميّتة بثمن الكشف، وأوّلُ ما يُطالَب به استردادٌ.
  const bg = stale ? '#ECEFEE' : (meta?.bg ?? '#ECEFEE')
  const color = stale ? '#8A9793' : (meta?.color ?? '#566460')
  const dot = stale ? '#C4CCC9' : (meta?.dot ?? '#8A9793')

  return (
    <span
      className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium"
      style={{ background: bg, color }}
      title={stale ? 'لم يُحدِّث صاحبُ الملفّ حالته منذ مدّة' : undefined}
    >
      <span className="size-1.5 rounded-full" style={{ background: dot }} />
      {stale ? 'حالة قديمة' : status.label}
    </span>
  )
}

/**
 * ★ عمر الشارة — ما يفرّق الخبر من الذكرى.
 *
 * الشركة تدفع رسم الكشف على أساس هذه الحالة، فمن حقّها أن تعرف متى أقرّها
 * صاحبُها. ولا شيء يُعرض لمن لم يضبط مؤشّره قطّ: «لم يُسأل بعدُ» غيرُ «قديم».
 */
export function StatusAge({ days, stale }: { days?: number | null; stale?: boolean }) {
  if (days === undefined || days === null || stale === true) return null

  return <span className="text-[10px] text-[var(--k-text-muted)]">{formatStatusAge(days)}</span>
}

export function SkillChip({ children }: { children: string }) {
  return (
    <span className="rounded-[7px] bg-canvas px-2.5 py-1 text-[11px] font-medium text-[var(--k-text-secondary)]">
      {children}
    </span>
  )
}
