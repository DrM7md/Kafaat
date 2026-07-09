import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router'
import { api } from '@/shared/api/client'
import type { Candidate } from '@/shared/api/types'
import { MaskedAvatar } from '@/shared/ui/MaskedAvatar'
import { StatusBadge, SkillChip } from '@/shared/ui/StatusBadge'
import { RevealButton } from '@/shared/ui/RevealButton'
import { useReveal } from './useReveal'
import { formatRef, formatSalary, formatYears } from '@/shared/lib/format'

export function CandidateDetailPage() {
  const { ref = '' } = useParams()
  const reveal = useReveal()

  const { data, isPending, isError } = useQuery({
    queryKey: ['candidates', 'detail', ref],
    queryFn: async () => (await api.get<{ data: Candidate }>(`/candidates/${ref}`)).data.data,
  })

  if (isPending) {
    return <p className="py-16 text-center text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>
  }

  // 404 هنا قد يعني «غير موجود» أو «أنت صاحب عمله الحالي».
  // لا نميّز بينهما: التمييز نفسه تسريب (ADR-004).
  if (isError || data === undefined) {
    return (
      <div className="grid place-items-center rounded-[20px] border border-dashed border-[var(--k-border-strong)] p-16 text-center">
        <p className="font-heading text-lg text-ink">هذا الملفّ غير متاح</p>
        <Link to="/candidates" className="mt-3 text-sm font-medium">
          العودة إلى التصفّح
        </Link>
      </div>
    )
  }

  const revealed = data.is_revealed

  return (
    <article className="mx-auto max-w-3xl overflow-hidden rounded-[24px] border border-[var(--k-border)] bg-surface shadow-[var(--k-shadow-hero)]">
      <header
        className="relative h-40"
        style={{ background: 'radial-gradient(120% 120% at 70% 0%, #12665C, var(--k-primary-dark))' }}
      >
        <div
          className="absolute inset-0 opacity-50"
          style={{ background: 'radial-gradient(60% 80% at 30% 120%, rgb(180 134 59 / 0.45), transparent)' }}
        />
        <div className="absolute inset-x-0 -bottom-10 flex justify-center">
          <div className="rounded-[26px] border-4 border-white shadow-lg">
            <MaskedAvatar initials={data.initials} size={88} />
          </div>
        </div>
      </header>

      <div className="px-7 pb-7 pt-14 text-center">
        {revealed && data.name !== undefined ? (
          <h1 className="font-heading text-2xl text-ink" style={{ animation: 'kf-rise .5s ease both' }}>
            {data.name}
          </h1>
        ) : (
          <h1 className="font-heading text-2xl text-[var(--k-text-muted)]">هوية محجوبة</h1>
        )}

        <p className="mt-1.5 text-sm font-semibold text-primary">{data.title}</p>
        <p className="mt-1.5 font-mono text-[11px] text-[var(--k-text-muted)]">{formatRef(data.ref)}</p>

        <div className="mt-3 flex justify-center">
          <StatusBadge status={data.employment_status} />
        </div>

        <div className="my-5 flex overflow-hidden rounded-[14px] border border-[var(--k-border)] bg-surface-muted">
          <Stat label="سنوات خبرة" value={formatYears(data.years_experience)} />
          <Divider />
          <Stat label="الموقع" value={data.city ?? '—'} />
          <Divider />
          <Stat label="الراتب المتوقّع" value={formatSalary(data.expected_salary)} />
        </div>

        {data.summary != null && (
          <p className="mb-5 text-right text-[13px] leading-7 text-[var(--k-text-secondary)]">{data.summary}</p>
        )}

        {data.skills !== undefined && data.skills.length > 0 && (
          <div className="mb-5 flex flex-wrap justify-center gap-1.5">
            {data.skills.map((skill) => (
              <SkillChip key={skill}>{skill}</SkillChip>
            ))}
          </div>
        )}

        {data.experiences !== undefined && data.experiences.length > 0 && (
          <section className="mb-5 text-right">
            <h2 className="mb-2 font-heading text-base text-ink">الخبرات</h2>
            <ul className="flex flex-col gap-3">
              {data.experiences.map((exp, i) => (
                <li key={i} className="rounded-xl border border-[var(--k-border)] p-3.5">
                  <p className="text-[13px] font-semibold text-ink">{exp.title}</p>
                  <p className="mt-0.5 text-xs text-[var(--k-text-secondary)]">
                    {/* اسم جهة العمل الحالية غائب قبل الكشف — لا فارغ. */}
                    {exp.company_name ?? (exp.is_current ? 'جهة العمل الحالية — محجوبة' : '—')}
                  </p>
                </li>
              ))}
            </ul>
          </section>
        )}

        {revealed && (data.phone !== undefined || data.email !== undefined) && (
          <div
            className="mb-4 flex flex-col gap-2.5 rounded-[14px] border p-4 text-right"
            style={{
              background: 'var(--k-gold-tint)',
              borderColor: 'rgb(180 134 59 / 0.35)',
              animation: 'kf-rise .5s ease both',
            }}
          >
            {data.email !== undefined && (
              <p dir="ltr" className="text-right text-[13px] font-medium text-ink">
                {data.email}
              </p>
            )}
            {data.phone !== undefined && (
              <p dir="ltr" className="text-right text-[13px] font-medium text-ink">
                {data.phone}
              </p>
            )}
          </div>
        )}

        <RevealButton
          revealed={revealed}
          fee={data.reveal_fee}
          pending={reveal.isPending}
          onReveal={() => reveal.mutate(data.ref)}
        />

        <p className="mt-3 text-[11px] text-[var(--k-text-muted)]">
          تُخصم رسوم الكشف مرّة واحدة لكل مرشّح · قابل للاسترداد وفق السياسة
        </p>
      </div>
    </article>
  )
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex-1 px-2 py-3.5">
      <div className="numeric text-[15px] font-bold text-ink">{value}</div>
      <div className="mt-0.5 text-[10px] text-[var(--k-text-muted)]">{label}</div>
    </div>
  )
}

function Divider() {
  return <div className="w-px bg-[var(--k-divider)]" />
}
