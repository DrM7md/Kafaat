import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiErrorMessage } from '@/shared/api/client'
import { formatMoney, formatNumber } from '@/shared/lib/format'
import { BackToSettings } from './BackToSettings'

type FieldPrice = {
  id: number
  label: string
  /** السعر المكتوب صراحةً. `null` = يرث الافتراضي. */
  fee: number | null
  /** ما يُخصَم فعلاً — الخاصّ إن وُجد، وإلّا الافتراضي. */
  effective_fee: number
  candidates: number
}

type Response = { fields: FieldPrice[]; default_fee: number }

/**
 * تسعير الكشف لكل مجال.
 *
 * ⚠️ التغيير هنا لا يمسّ فاتورةً قائمة: السعر يُجمَّد لحظة إنشائها (ADR-006).
 *    رفعُ سعر مجالٍ اليوم لا يُعيد تسعير كشفِ أمس.
 */
export function RevealPricingPage() {
  const queryClient = useQueryClient()
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState<number | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'reveal-pricing'],
    queryFn: async () => (await api.get<Response>('/admin/reveal-pricing')).data,
  })

  const save = useMutation({
    mutationFn: async (v: { id: number; fee: number | null }) =>
      api.put(`/admin/reveal-pricing/${v.id}`, { fee: v.fee }),
    onSuccess: async (_res, v) => {
      setError(null)
      setSaved(v.id)
      await queryClient.invalidateQueries({ queryKey: ['admin', 'reveal-pricing'] })

      // بطاقات التصفّح تعرض السعر — لا تُترك قديمةً في ذاكرة الواجهة.
      await queryClient.invalidateQueries({ queryKey: ['candidates'] })
    },
    onError: (err) => setError(apiErrorMessage(err)),
  })

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6">
      <header>
        <BackToSettings />
        <h1 className="font-heading text-2xl text-ink">أسعار الكشف</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          سعرٌ لكل مجال. المجال بلا سعرٍ خاصّ يرث الافتراضي (
          <strong className="numeric">{formatMoney(data?.default_fee ?? 0, 'QAR')}</strong>) — تُغيّره من الإعدادات.
        </p>
      </header>

      {error !== null && (
        <p className="rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
          {error}
        </p>
      )}

      <p className="rounded-xl bg-surface-muted p-3.5 text-[12.5px] text-[var(--k-text-secondary)]">
        تغيير السعر <strong>لا يمسّ فاتورةً قائمة</strong>: السعر يُجمَّد لحظة الدفع. ما تُغيّره اليوم يسري على
        الكشوف الجديدة وحدها، وكل تغيير يُكتب في سجلّ التدقيق.
      </p>

      <section className="overflow-hidden rounded-[24px] bg-surface shadow-[var(--k-sh-2)]">
        {isPending ? (
          <p className="p-8 text-center text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>
        ) : (
          <table className="w-full text-right">
            <thead className="bg-surface-muted text-[11px] text-[var(--k-text-muted)]">
              <tr>
                <th className="p-3 font-medium">المجال</th>
                <th className="p-3 font-medium">المرشّحون</th>
                <th className="p-3 font-medium">السعر</th>
                <th className="p-3 font-medium">المُطبَّق</th>
                <th className="p-3" />
              </tr>
            </thead>
            <tbody>
              {(data?.fields ?? []).map((row) => (
                <PriceRow
                  key={row.id}
                  row={row}
                  defaultFee={data?.default_fee ?? 0}
                  justSaved={saved === row.id}
                  pending={save.isPending}
                  onSave={(fee) => save.mutate({ id: row.id, fee })}
                />
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  )
}

function PriceRow({
  row,
  defaultFee,
  justSaved,
  pending,
  onSave,
}: {
  row: FieldPrice
  defaultFee: number
  justSaved: boolean
  pending: boolean
  onSave: (fee: number | null) => void
}) {
  const [draft, setDraft] = useState<string>(row.fee === null ? '' : String(row.fee))

  const parsed = draft.trim() === '' ? null : Number(draft.replace(/\D/g, ''))
  const dirty = parsed !== row.fee

  return (
    <tr className="border-t border-[var(--k-divider)]">
      <td className="p-3">
        <p className="text-[13px] font-medium text-ink">{row.label}</p>
        {row.fee === null && <p className="text-[11px] text-[var(--k-text-muted)]">يرث الافتراضي</p>}
      </td>

      <td className="numeric p-3 text-[12px] text-[var(--k-text-secondary)]">{formatNumber(row.candidates)}</td>

      <td className="p-3">
        <div className="flex w-32 items-center rounded-lg border border-[var(--k-border-strong)] bg-surface focus-within:border-primary">
          <input
            type="text"
            inputMode="numeric"
            value={draft}
            onChange={(e) => setDraft(e.target.value.replace(/\D/g, ''))}
            placeholder={String(defaultFee)}
            className="numeric min-w-0 flex-1 bg-transparent px-2.5 py-1.5 text-[13px] outline-none"
            aria-label={`سعر كشف ${row.label}`}
          />
          <span className="shrink-0 border-r border-[var(--k-divider)] px-2 py-1.5 text-[11px] text-[var(--k-text-muted)]">
            ر.ق
          </span>
        </div>
      </td>

      <td className="numeric p-3 text-[13px] font-semibold text-ink">{formatMoney(row.effective_fee, 'QAR')}</td>

      <td className="p-3">
        {dirty ? (
          <button
            type="button"
            disabled={pending}
            onClick={() => onSave(parsed)}
            className="cursor-pointer rounded-lg bg-primary px-3 py-1.5 text-[12px] font-semibold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0 disabled:opacity-50"
          >
            حفظ
          </button>
        ) : justSaved ? (
          <span className="text-[11px] text-[#1E7A44]">حُفظ ✓</span>
        ) : (
          row.fee !== null && (
            <button
              type="button"
              disabled={pending}
              onClick={() => {
                setDraft('')
                onSave(null)
              }}
              className="cursor-pointer text-[12px] text-[var(--k-text-secondary)] hover:underline disabled:opacity-50"
            >
              إعادة للافتراضي
            </button>
          )
        )}
      </td>
    </tr>
  )
}
