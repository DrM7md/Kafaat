import { useQuery } from '@tanstack/react-query'
import { api } from '@/shared/api/client'
import { formatQar } from '@/shared/lib/format'

type Reveal = {
  id: number
  status: 'pending' | 'completed' | 'failed' | 'refunded'
  amount: number
  currency: string
  revealed_at: string | null
  employee_profile: { ref: string; title: string } | null
}

const STATUS: Record<Reveal['status'], { label: string; bg: string; color: string }> = {
  pending: { label: 'بانتظار الدفع', bg: '#FBF0D9', color: '#96682A' },
  completed: { label: 'مكتمل', bg: '#E7F3EA', color: '#1E7A44' },
  failed: { label: 'فاشل', bg: '#ECEFEE', color: '#566460' },
  refunded: { label: 'مُسترَدّ', bg: '#ECEFEE', color: '#566460' },
}

export function MyRevealsPage() {
  const { data, isPending, isError } = useQuery({
    queryKey: ['reveals'],
    queryFn: async () => (await api.get<{ data: Reveal[] }>('/reveals')).data.data,
  })

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="font-heading text-2xl text-ink">كشوفاتي</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          المبلغ المعروض هو السعر المجمّد وقت الكشف — لا يتغيّر بتغيير الرسم لاحقاً.
        </p>
      </header>

      {isPending && <p className="text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>}

      {isError && (
        <div className="grid place-items-center rounded-[16px] border border-dashed border-[var(--k-border-strong)] p-12 text-center">
          <p className="font-heading text-lg text-ink">تعذّر تحميل كشوفاتك</p>
          <p className="mt-1 text-sm text-[var(--k-text-secondary)]">تأكّد من الاتصال ثم أعد المحاولة.</p>
        </div>
      )}

      {data !== undefined && data.length === 0 && (
        <p className="rounded-[16px] border border-dashed border-[var(--k-border-strong)] p-12 text-center text-sm text-[var(--k-text-muted)]">
          لم تكشف أي مرشّح بعد.
        </p>
      )}

      {data !== undefined && data.length > 0 && (
        <div className="overflow-hidden rounded-[20px] border border-[var(--k-border)] bg-surface">
          {data.map((reveal) => {
            const status = STATUS[reveal.status]

            return (
              <div
                key={reveal.id}
                className="flex items-center justify-between gap-4 border-b border-[var(--k-divider)] p-4 last:border-0"
              >
                <div className="min-w-0">
                  <p className="truncate text-[13px] font-semibold text-ink">
                    {reveal.employee_profile?.title ?? 'ملفّ محذوف'}
                  </p>
                  <code dir="ltr" className="text-[11px] text-[var(--k-text-muted)]">
                    {reveal.employee_profile?.ref ?? '—'}
                  </code>
                </div>

                <span
                  className="rounded-full px-2.5 py-1 text-[11px] font-medium"
                  style={{ background: status.bg, color: status.color }}
                >
                  {status.label}
                </span>

                <span className="numeric text-[13px] font-semibold text-ink">{formatQar(reveal.amount)}</span>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
