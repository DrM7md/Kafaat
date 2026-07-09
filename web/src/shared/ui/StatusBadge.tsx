import type { Lookup } from '@/shared/api/types'

/**
 * شارة الحالة الوظيفية.
 *
 * الألوان تأتي من `lookups.meta` عبر الـ API لا من CSS — وهذا ما يجعل
 * «إضافة حالة رابعة بلونها» عملاً إدارياً لا برمجياً.
 * Brain/05-settings-engine.md §4.
 */
export function StatusBadge({ status }: { status?: Lookup }) {
  if (status === undefined) return null

  const meta = status.meta ?? { bg: '#ECEFEE', color: '#566460', dot: '#8A9793' }

  return (
    <span
      className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium"
      style={{ background: meta.bg, color: meta.color }}
    >
      <span className="size-1.5 rounded-full" style={{ background: meta.dot }} />
      {status.label}
    </span>
  )
}

export function SkillChip({ children }: { children: string }) {
  return (
    <span className="rounded-[7px] bg-canvas px-2.5 py-1 text-[11px] font-medium text-[var(--k-text-secondary)]">
      {children}
    </span>
  )
}
