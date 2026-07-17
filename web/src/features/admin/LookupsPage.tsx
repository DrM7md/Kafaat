import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiErrorMessage } from '@/shared/api/client'
import { BackToSettings } from './BackToSettings'

type AdminLookup = {
  id: number
  group: string
  key: string
  label_ar: string
  label_en: string | null
  parent_id: number | null
  parent_label: string | null
  sort_order: number
  is_active: boolean
  status: 'approved' | 'pending' | 'rejected'
  meta: Record<string, string> | null
  suggested_by?: string | null
  created_at: string | null
}

type Response = {
  lookups: AdminLookup[]
  groups: string[]
  pending_count: number
}

const GROUP_LABELS: Record<string, string> = {
  field: 'المجالات',
  city: 'المدن',
  country: 'الدول',
  nationality: 'الجنسيات',
  employment_status: 'الحالات الوظيفية',
  industry: 'قطاعات الشركات',
  company_size: 'أحجام الشركات',
}

/**
 * إدارة القوائم المرجعية.
 *
 * هذه الصفحة هي ما يجعل «أضِف مجالاً» و«افتح الإمارات بدرهمها» عملاً
 * إدارياً بنقرة، لا مهاجرةً ونشرَ كود. Brain/05-settings-engine.md §4.
 *
 * وفيها يمرّ طابور الاقتراحات: ما كتبه الموظّفون في «أخرى» لا يصير خياراً
 * للجميع حتى يمرّ من هنا.
 */
export function LookupsPage() {
  const queryClient = useQueryClient()
  const [group, setGroup] = useState('field')
  const [error, setError] = useState<string | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'lookups', group],
    queryFn: async () => (await api.get<Response>('/admin/lookups', { params: { group } })).data,
  })

  async function refresh() {
    setError(null)
    await queryClient.invalidateQueries({ queryKey: ['admin', 'lookups'] })

    // القائمة العامّة تغيّرت للجميع — لا تُترك قديمةً في ذاكرة الواجهة.
    await queryClient.invalidateQueries({ queryKey: ['lookups'] })
  }

  const mutate = useMutation({
    mutationFn: async (op: { method: 'post' | 'put' | 'delete'; url: string; body?: unknown }) =>
      op.method === 'delete' ? api.delete(op.url) : api[op.method](op.url, op.body ?? {}),
    onSuccess: refresh,
    onError: (err) => setError(apiErrorMessage(err)),
  })

  const rows = data?.lookups ?? []
  const pending = rows.filter((l) => l.status === 'pending')
  const rest = rows.filter((l) => l.status !== 'pending')

  return (
    <div className="mx-auto flex max-w-4xl flex-col gap-6">
      <header>
        <BackToSettings />
        <h1 className="font-heading text-2xl text-ink">القوائم المرجعية</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          المجالات والمدن والدول. ما تضيفه هنا يظهر في نماذج الجميع فوراً — بلا نشر كود.
        </p>
      </header>

      {error !== null && (
        <p className="rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
          {error}
        </p>
      )}

      <div className="flex flex-wrap gap-2">
        {(data?.groups ?? Object.keys(GROUP_LABELS)).map((g) => (
          <button
            key={g}
            type="button"
            onClick={() => setGroup(g)}
            className={`cursor-pointer rounded-full border px-3.5 py-2 text-xs font-medium ${
              g === group
                ? 'border-primary bg-mint text-primary'
                : 'border-[var(--k-border-strong)] bg-surface text-[var(--k-text-secondary)]'
            }`}
          >
            {GROUP_LABELS[g] ?? g}
          </button>
        ))}
      </div>

      {pending.length > 0 && (
        <section className="rounded-[20px] border border-[rgb(180_134_59/0.35)] bg-gold-tint p-5">
          <h2 className="font-heading text-base text-[#96682A]">
            اقتراحات تنتظر قرارك ({pending.length})
          </h2>
          <p className="mt-1 text-[12.5px] text-[#96682A]">
            كتبها موظّفون عبر «أخرى». لا يراها غيرهم حتى تعتمدها.
          </p>

          <ul className="mt-4 flex flex-col gap-2">
            {pending.map((row) => (
              <li
                key={row.id}
                className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface p-3"
              >
                <div className="min-w-0">
                  <p className="text-[13px] font-semibold text-ink">{row.label_ar}</p>
                  <p className="text-[11px] text-[var(--k-text-muted)]">
                    {row.parent_label !== null && `${row.parent_label} · `}
                    اقترحها {row.suggested_by ?? '—'}
                  </p>
                </div>

                <div className="flex shrink-0 gap-2">
                  <button
                    type="button"
                    disabled={mutate.isPending}
                    onClick={() => mutate.mutate({ method: 'post', url: `/admin/lookups/${row.id}/approve` })}
                    className="cursor-pointer rounded-lg bg-primary px-3 py-1.5 text-[12px] font-semibold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0 disabled:opacity-50"
                  >
                    اعتماد
                  </button>
                  <button
                    type="button"
                    disabled={mutate.isPending}
                    onClick={() => mutate.mutate({ method: 'post', url: `/admin/lookups/${row.id}/reject` })}
                    className="cursor-pointer rounded-lg border border-[var(--k-border-strong)] px-3 py-1.5 text-[12px] text-[var(--k-text-secondary)] disabled:opacity-50"
                  >
                    رفض
                  </button>
                </div>
              </li>
            ))}
          </ul>
        </section>
      )}

      <AddRow
        group={group}
        onAdd={(body) => mutate.mutate({ method: 'post', url: '/admin/lookups', body })}
        pending={mutate.isPending}
      />

      <section className="overflow-hidden rounded-[24px] bg-surface shadow-[var(--k-sh-2)]">
        {isPending ? (
          <p className="p-8 text-center text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>
        ) : rest.length === 0 ? (
          <p className="p-8 text-center text-sm text-[var(--k-text-muted)]">لا عناصر في هذه القائمة بعد.</p>
        ) : (
          <table className="w-full text-right">
            <thead className="bg-surface-muted text-[11px] text-[var(--k-text-muted)]">
              <tr>
                <th className="p-3 font-medium">التسمية</th>
                <th className="p-3 font-medium">تتبع</th>
                <th className="p-3 font-medium">الترتيب</th>
                <th className="p-3 font-medium">الحالة</th>
                <th className="p-3" />
              </tr>
            </thead>
            <tbody>
              {rest.map((row) => (
                <tr key={row.id} className="border-t border-[var(--k-divider)]">
                  <td className="p-3">
                    <p className="text-[13px] font-medium text-ink">{row.label_ar}</p>
                    <p className="text-[11px] text-[var(--k-text-muted)]">
                      {row.label_en ?? row.key}
                      {row.meta?.currency !== undefined && ` · ${row.meta.currency}`}
                    </p>
                  </td>
                  <td className="p-3 text-[12px] text-[var(--k-text-secondary)]">{row.parent_label ?? '—'}</td>
                  <td className="numeric p-3 text-[12px] text-[var(--k-text-muted)]">{row.sort_order}</td>
                  <td className="p-3">
                    {row.status === 'rejected' ? (
                      <span className="text-[11px] text-[#8a2b2b]">مرفوض</span>
                    ) : row.is_active ? (
                      <span className="text-[11px] text-[#1E7A44]">نشط</span>
                    ) : (
                      <span className="text-[11px] text-[var(--k-text-muted)]">معطّل</span>
                    )}
                  </td>
                  <td className="p-3">
                    <button
                      type="button"
                      disabled={mutate.isPending || row.status === 'rejected'}
                      onClick={() =>
                        mutate.mutate({
                          method: 'put',
                          url: `/admin/lookups/${row.id}`,
                          body: { is_active: !row.is_active },
                        })
                      }
                      className="cursor-pointer text-[12px] font-medium text-primary hover:underline disabled:opacity-40"
                    >
                      {row.is_active ? 'تعطيل' : 'تفعيل'}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>

      <p className="text-[11px] text-[var(--k-text-muted)]">
        التعطيل يُخفي القيمة من النماذج ويُبقي بيانات من اختارها سليمة — وهو المقصود غالباً، لا الحذف.
      </p>
    </div>
  )
}

function AddRow({
  group,
  onAdd,
  pending,
}: {
  group: string
  onAdd: (body: Record<string, unknown>) => void
  pending: boolean
}) {
  const [label, setLabel] = useState('')

  function submit(event: React.FormEvent) {
    event.preventDefault()
    if (label.trim() === '') return

    onAdd({ group, label_ar: label.trim() })
    setLabel('')
  }

  return (
    <form onSubmit={submit} className="flex gap-2 rounded-[24px] bg-surface p-4 shadow-[var(--k-sh-2)]">
      <input
        value={label}
        onChange={(e) => setLabel(e.target.value)}
        placeholder={`أضِف إلى «${GROUP_LABELS[group] ?? group}»…`}
        className="min-w-0 flex-1 rounded-xl border border-[var(--k-border-strong)] bg-surface px-3.5 py-2.5 text-sm outline-none focus:border-primary"
      />
      <button
        type="submit"
        disabled={pending || label.trim() === ''}
        className="shrink-0 cursor-pointer rounded-xl bg-primary px-4 py-2.5 text-[13px] font-semibold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0 disabled:opacity-50"
      >
        إضافة
      </button>
    </form>
  )
}
