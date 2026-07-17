import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router'
import { api } from '@/shared/api/client'
import { useAuth } from '@/shared/auth/AuthProvider'
import { RevealBadge, VerificationBadge } from './shared'
import { formatNumber, formatQar } from '@/shared/lib/format'
import type { Company } from './CompaniesPage'

type Reveal = {
  id: number
  status: string
  amount: number
  currency: string
  source: string
  revealed_at: string | null
  created_at: string | null
  candidate: { ref: string; title: string }
  payment: { provider: string; status: string } | null
}

type Detail = {
  company: Company & { phone: string | null; industry: string | null; size: string | null; verified_at: string | null }
  reveals: Reveal[]
  stats: { total_reveals: number; completed: number; total_spent: number }
}

export function CompanyDetailPage() {
  const { id = '' } = useParams()
  const queryClient = useQueryClient()
  const { can } = useAuth()

  const { data, isPending, isError } = useQuery({
    queryKey: ['admin', 'companies', id],
    queryFn: async () => (await api.get<Detail>(`/admin/companies/${id}`)).data,
  })

  const verify = useMutation({
    mutationFn: async (status: string) => api.put(`/admin/companies/${id}/verification`, { status }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin', 'companies'] })
    },
  })

  if (isPending) {
    return <p className="py-16 text-center text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>
  }

  if (isError || data === undefined) {
    return (
      <div className="grid place-items-center rounded-[24px] border border-dashed border-[var(--k-border-strong)] p-16 text-center">
        <p className="font-heading text-lg text-ink">هذه الشركة غير متاحة</p>
        <Link to="/admin/companies" className="mt-3 text-sm font-medium">
          العودة إلى القائمة
        </Link>
      </div>
    )
  }

  const { company, reveals, stats } = data

  return (
    <div className="flex flex-col gap-6">
      <nav className="text-xs text-[var(--k-text-muted)]">
        <Link to="/admin/companies">الشركات</Link> · <span className="text-ink">{company.legal_name}</span>
      </nav>

      <header className="rounded-[24px] bg-surface p-6 shadow-[var(--k-sh-2)]">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-3">
              <h1 className="font-heading text-2xl text-ink">{company.legal_name}</h1>
              <VerificationBadge status={company.verification_status} />
            </div>

            <dl className="mt-4 grid gap-x-8 gap-y-2 text-[13px] sm:grid-cols-2">
              <Row label="المسؤول" value={company.contact_name} />
              <Row label="البريد" value={company.email} ltr />
              <Row label="الهاتف" value={company.phone ?? '—'} ltr />
              <Row label="السجلّ التجاري" value={company.cr_number ?? '—'} ltr />
              <Row label="المجال" value={company.industry ?? '—'} />
              <Row
                label="التسجيل"
                value={company.created_at === null ? '—' : new Date(company.created_at).toLocaleDateString('ar-QA')}
              />
            </dl>
          </div>

          {can('company.approve') && (
            <div className="flex gap-2">
              {company.verification_status !== 'verified' && (
                <button
                  type="button"
                  disabled={verify.isPending}
                  onClick={() => verify.mutate('verified')}
                  className="cursor-pointer rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0 disabled:opacity-50"
                >
                  توثيق الشركة
                </button>
              )}
              {company.verification_status !== 'rejected' && (
                <button
                  type="button"
                  disabled={verify.isPending}
                  onClick={() => verify.mutate('rejected')}
                  className="cursor-pointer rounded-xl border border-[var(--k-border-strong)] px-4 py-2.5 text-sm font-medium transition hover:-translate-y-0.5 hover:bg-surface-muted disabled:opacity-50"
                >
                  رفض
                </button>
              )}
            </div>
          )}
        </div>
      </header>

      <div className="grid gap-4 sm:grid-cols-3">
        <Stat label="إجمالي الطلبات" value={formatNumber(stats.total_reveals)} />
        <Stat label="كشوفات مكتملة" value={formatNumber(stats.completed)} />
        <Stat label="إجمالي المدفوع" value={formatQar(stats.total_spent)} accent />
      </div>

      <section>
        <h2 className="mb-3 font-heading text-lg text-ink">طلبات الكشف</h2>

        {reveals.length === 0 ? (
          <p className="rounded-[20px] border border-dashed border-[var(--k-border-strong)] p-10 text-center text-sm text-[var(--k-text-muted)]">
            لم تطلب هذه الشركة أي كشف بعد.
          </p>
        ) : (
          <div className="overflow-hidden rounded-[24px] bg-surface shadow-[var(--k-sh-2)]">
            <table className="w-full text-right text-[13px]">
              <thead className="bg-surface-muted text-[11px] text-[var(--k-text-muted)]">
                <tr>
                  <th className="p-3 font-medium">المرشّح</th>
                  <th className="p-3 font-medium">الحالة</th>
                  <th className="p-3 font-medium">المبلغ</th>
                  <th className="p-3 font-medium">البوّابة</th>
                  <th className="p-3 font-medium">التاريخ</th>
                </tr>
              </thead>
              <tbody>
                {reveals.map((reveal) => (
                  <tr key={reveal.id} className="border-t border-[var(--k-divider)]">
                    <td className="p-3">
                      <Link to={`/candidates/${reveal.candidate.ref}`} className="font-medium">
                        {reveal.candidate.title}
                      </Link>
                      <code dir="ltr" className="mr-2 text-[11px] text-[var(--k-text-muted)]">
                        {reveal.candidate.ref}
                      </code>
                    </td>
                    <td className="p-3">
                      <RevealBadge status={reveal.status} />
                    </td>
                    <td className="numeric p-3 font-semibold text-ink">{formatQar(reveal.amount)}</td>
                    <td className="p-3 text-[11px] text-[var(--k-text-muted)]">
                      {reveal.payment?.provider ?? reveal.source}
                    </td>
                    <td className="numeric p-3 text-[11px] text-[var(--k-text-muted)]">
                      {reveal.created_at === null ? '—' : new Date(reveal.created_at).toLocaleDateString('ar-QA')}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <p className="mt-3 text-[11px] text-[var(--k-text-muted)]">
          المبالغ مجمّدة وقت تنفيذ كل كشف — لا تتغيّر بتغيير الرسم لاحقاً.
        </p>
      </section>
    </div>
  )
}

function Row({ label, value, ltr = false }: { label: string; value: string; ltr?: boolean }) {
  return (
    <div className="flex gap-2">
      <dt className="text-[var(--k-text-muted)]">{label}:</dt>
      <dd dir={ltr ? 'ltr' : undefined} className={ltr ? 'text-right font-medium' : 'font-medium'}>
        {value}
      </dd>
    </div>
  )
}

function Stat({ label, value, accent = false }: { label: string; value: string; accent?: boolean }) {
  return (
    <div className="rounded-[20px] bg-surface p-4 shadow-[var(--k-sh-2)]">
      <p className="text-[11px] text-[var(--k-text-muted)]">{label}</p>
      <p className={`numeric mt-1 text-xl font-bold ${accent ? 'text-primary' : 'text-ink'}`}>{value}</p>
    </div>
  )
}
