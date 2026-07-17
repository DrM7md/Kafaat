import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { apiErrorMessage } from '@/shared/api/client'
import type { Job } from '@/shared/api/types'
import { formatMoney, formatNumber, formatYears } from '@/shared/lib/format'
import { useDebounced } from '@/shared/hooks/useDebounced'
import { jobBoardQuery, useJobInterest } from './queries'

/**
 * لوحة الوظائف — ما يراه الباحث عن عمل.
 *
 * ★★ زرّ «مهتمّ» لا يكشف هويّتك. الشركة تُبلَّغ بأنّ «مرشّحاً» مهتمّ، وترى
 *    بطاقتك محجوبة وزرَّ كشف. النصّ تحت الزرّ يقول ذلك صراحةً — فلا يضغطه
 *    أحدٌ ظانّاً أنّه يرسل سيرته الذاتية باسمه.
 */
export function JobBoardPage() {
  const [q, setQ] = useState('')
  const debounced = useDebounced(q)

  const { data, isPending } = useQuery(jobBoardQuery({ q: debounced }))

  const jobs = data?.data ?? []

  return (
    <div className="mx-auto flex max-w-4xl flex-col gap-5">
      <header>
        <h1 className="font-heading text-2xl text-ink">الوظائف المطلوبة</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          متطلّبات الشركات. أبدِ اهتمامك — <strong>هويّتك تبقى محجوبة</strong> حتى تدفع الشركةُ رسم الكشف.
        </p>
      </header>

      <input
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder="ابحث بالمسمّى أو الوصف…"
        className="w-full rounded-full border border-[var(--k-border-strong)] bg-surface px-4 py-2.5 text-sm outline-none focus:border-primary"
      />

      {isPending ? (
        <p className="py-16 text-center text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>
      ) : jobs.length === 0 ? (
        <div className="grid place-items-center rounded-[24px] border border-dashed border-[var(--k-border-strong)] p-14 text-center">
          <p className="font-heading text-base text-ink">لا وظائف مطابقة</p>
          <p className="mt-1 text-[13px] text-[var(--k-text-secondary)]">
            سنعرض هنا متطلّبات الشركات فور نشرها.
          </p>
        </div>
      ) : (
        <>
          <p className="numeric text-[13px] text-[var(--k-text-secondary)]">
            {formatNumber(data?.meta.total ?? 0)} وظيفة
          </p>

          <ul className="grid gap-4 sm:grid-cols-2">
            {jobs.map((job) => (
              <li key={job.ref}>
                <JobCard job={job} />
              </li>
            ))}
          </ul>
        </>
      )}
    </div>
  )
}

export function JobCard({ job }: { job: Job }) {
  const interest = useJobInterest()
  const interested = job.has_interest === true

  return (
    <article className="flex h-full flex-col gap-3 rounded-[20px] bg-surface p-[18px] shadow-[var(--k-sh-2)] transition-[transform,box-shadow] duration-200 hover:-translate-y-1 hover:shadow-[var(--k-shadow-hover)]">
      <div>
        <Link
          to={`/jobs/${job.ref}`}
          className="block text-[15px] font-semibold text-ink hover:text-primary"
        >
          {job.title}
        </Link>

        {/* اسم الشركة مكشوف — الحجب يحمي الموظّف من صاحب عمله، لا الشركةَ
            من الباحثين. من ينشر وظيفةً يريد أن يُعرَف. */}
        <p className="mt-0.5 text-[12.5px] font-medium text-primary">
          {job.company?.brand_name ?? job.company?.legal_name}
        </p>
      </div>

      <p className="line-clamp-2 text-[12.5px] leading-6 text-[var(--k-text-secondary)]">
        {job.description}
      </p>

      <div className="flex flex-wrap gap-3 text-xs text-[var(--k-text-secondary)]">
        {job.city != null && <span>{job.city}</span>}
        {job.field != null && <span>{job.field.label}</span>}
        {job.min_experience > 0 && <span>{formatYears(job.min_experience)}+ خبرة</span>}
      </div>

      {job.salary !== null && (
        <p className="numeric text-[13px] font-semibold text-ink">
          {job.salary.min !== null && job.salary.max !== null
            ? `${formatMoney(job.salary.min, job.salary.currency)} – ${formatMoney(job.salary.max, job.salary.currency)}`
            : formatMoney(job.salary.min ?? job.salary.max ?? 0, job.salary.currency)}
        </p>
      )}

      <div className="mt-auto flex flex-col gap-1.5 border-t border-[var(--k-divider)] pt-3">
        <button
          type="button"
          disabled={interest.isPending}
          onClick={() => interest.mutate({ ref: job.ref, interested: !interested })}
          className={`cursor-pointer rounded-xl px-4 py-2.5 text-[13px] font-semibold disabled:opacity-50 ${
            interested
              ? 'bg-mint text-primary'
              : 'bg-primary text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0'
          }`}
        >
          {interest.isPending ? 'جارٍ…' : interested ? '✓ أبديتَ اهتمامك' : 'أنا مهتمّ'}
        </button>

        {/* ★★ الوعد الصريح. بلا هذا السطر يضغط الموظّف ظانّاً أنّه يرسل
            اسمه وسيرته — ثم يشعر بالخديعة حين لا يتصل به أحد. */}
        <p className="text-center text-[11px] text-[var(--k-text-muted)]">
          {interested
            ? 'أُبلغت الشركة. هويّتك محجوبة حتى تدفع رسم الكشف.'
            : 'اسمك ورقمك يبقيان محجوبين.'}
        </p>

        {interest.isError && (
          <p className="text-center text-[11px] text-[#96682A]">{apiErrorMessage(interest.error)}</p>
        )}
      </div>
    </article>
  )
}
