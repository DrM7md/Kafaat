import { useMemo, useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { apiErrorMessage } from '@/shared/api/client'
import type { Lookup } from '@/shared/api/types'
import { CandidateCard } from '@/shared/ui/CandidateCard'
import { useReveal } from './useReveal'
import { candidateListQuery, lookupsQuery, EMPTY_FILTERS, type Filters } from './queries'
import { usePrefetchCandidate } from './usePrefetchCandidate'
import { useBillingUnderDevelopment } from '@/shared/hooks/usePublicSettings'
import { useDebounced } from '@/shared/hooks/useDebounced'
import { formatNumber } from '@/shared/lib/format'

/** شبكة التصفّح — معالجة `1a` (ADR-008). */
export function BrowsePage() {
  const [filters, setFilters] = useState<Filters>(EMPTY_FILTERS)
  const reveal = useReveal()
  const billingUnderDevelopment = useBillingUnderDevelopment()
  const prefetchCandidate = usePrefetchCandidate()

  const lookups = useQuery(lookupsQuery())

  // الحقول النصّية تُؤخَّر، والقوائم المنسدلة تُطبَّق فوراً.
  //
  // بلا هذا كان `queryKey` يتغيّر مع كل حرف يُكتب، فيُطلَق طلبٌ لكل ضغطة:
  // كتابة «مهندس» = ستّة طلبات. الخطّاف كان موجوداً وتستعمله لوحة المشرف،
  // وصفحة التصفّح — وهي الأكثر استعمالاً — وحدها لم تكن تستعمله.
  const debouncedQ = useDebounced(filters.q)
  const debouncedMinExperience = useDebounced(filters.min_experience)

  const queryFilters = useMemo(
    (): Filters => ({ ...filters, q: debouncedQ, min_experience: debouncedMinExperience }),
    [filters, debouncedQ, debouncedMinExperience],
  )

  const candidates = useQuery({
    ...candidateListQuery(queryFilters),
    placeholderData: keepPreviousData,
  })

  return (
    <div className="rounded-[20px] border border-[var(--k-border)] bg-surface-alt p-5 shadow-[var(--k-shadow-container)]">
      <Toolbar
        filters={filters}
        lookups={lookups.data}
        onChange={setFilters}
        onReset={() => setFilters(EMPTY_FILTERS)}
      />

      {billingUnderDevelopment && (
        <p className="mb-4 rounded-xl border border-[var(--k-border)] bg-surface-muted p-3 text-sm text-[var(--k-text-secondary)]">
          الدفع قيد التطوير — سيُفعَّل عند ربط بوّابة الدفع.
        </p>
      )}

      {reveal.isError && (
        <p className="mb-4 rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
          {apiErrorMessage(reveal.error)}
        </p>
      )}

      {candidates.isPending && <SkeletonGrid />}

      {candidates.isError && (
        <EmptyState title="تعذّر تحميل الكفاءات" body="تأكّد من الاتصال ثم أعد المحاولة." />
      )}

      {candidates.data !== undefined && candidates.data.data.length === 0 && (
        <EmptyState title="لا كفاءات مطابقة" body="جرّب توسيع معايير البحث أو إزالة بعض الفلاتر." />
      )}

      {candidates.data !== undefined && candidates.data.data.length > 0 && (
        <>
          <p className="mb-4 text-[13px] font-semibold text-ink">
            {formatNumber(candidates.data.meta.total)} كفاءة مطابقة
          </p>

          <div className="grid gap-4 md:grid-cols-2">
            {candidates.data.data.map((candidate) => (
              <CandidateCard
                key={candidate.ref}
                candidate={candidate}
                onPrefetch={prefetchCandidate}
                pending={reveal.isPending && reveal.variables === candidate.ref}
                onReveal={(ref) => reveal.mutate(ref)}
              />
            ))}
          </div>
        </>
      )}
    </div>
  )
}

function Toolbar({
  filters,
  lookups,
  onChange,
  onReset,
}: {
  filters: Filters
  lookups?: Record<string, Lookup[]>
  onChange: (f: Filters) => void
  onReset: () => void
}) {
  const hasFilters = Object.values(filters).some((v) => v !== '')

  return (
    <div className="mb-5 flex flex-wrap items-center gap-3">
      <label className="flex min-w-[220px] flex-1 items-center gap-2.5 rounded-xl border border-[var(--k-border-strong)] bg-surface px-3.5 py-2.5">
        <SearchIcon />
        <input
          type="search"
          value={filters.q}
          onChange={(e) => onChange({ ...filters, q: e.target.value })}
          placeholder="ابحث بالمهارة، المجال، المسمّى…"
          className="w-full bg-transparent text-[13px] outline-none placeholder:text-[var(--k-text-muted)]"
        />
      </label>

      <Select
        value={filters.field}
        onChange={(v) => onChange({ ...filters, field: v })}
        options={lookups?.field ?? []}
        placeholder="كل المجالات"
      />
      <Select
        value={filters.city}
        onChange={(v) => onChange({ ...filters, city: v })}
        options={lookups?.city ?? []}
        placeholder="كل المدن"
      />
      <Select
        value={filters.status}
        onChange={(v) => onChange({ ...filters, status: v })}
        options={lookups?.employment_status ?? []}
        placeholder="كل الحالات"
      />

      <input
        type="number"
        min={0}
        max={60}
        value={filters.min_experience}
        onChange={(e) => onChange({ ...filters, min_experience: e.target.value })}
        placeholder="الخبرة 5+"
        className="w-28 rounded-full border border-[var(--k-border-strong)] bg-surface px-3.5 py-2 text-xs outline-none"
      />

      {hasFilters && (
        <button
          type="button"
          onClick={onReset}
          className="cursor-pointer rounded-full px-3 py-2 text-xs font-medium text-primary hover:bg-mint"
        >
          مسح الفلاتر
        </button>
      )}
    </div>
  )
}

function Select({
  value,
  onChange,
  options,
  placeholder,
}: {
  value: string
  onChange: (v: string) => void
  options: Lookup[]
  placeholder: string
}) {
  return (
    <select
      value={value}
      onChange={(e) => onChange(e.target.value)}
      aria-label={placeholder}
      className={`cursor-pointer rounded-full border px-3.5 py-2 text-xs font-medium outline-none ${
        value === ''
          ? 'border-[var(--k-border-strong)] bg-surface text-[var(--k-text-secondary)]'
          : 'border-[rgb(15_80_73/0.2)] bg-mint text-primary'
      }`}
    >
      <option value="">{placeholder}</option>
      {options.map((option) => (
        <option key={option.key} value={option.key}>
          {option.label}
        </option>
      ))}
    </select>
  )
}

function SkeletonGrid() {
  return (
    <div className="grid gap-4 md:grid-cols-2">
      {Array.from({ length: 4 }, (_, i) => (
        <div
          key={i}
          className="h-64 rounded-[16px] border border-[var(--k-border)] bg-surface"
          style={{
            background:
              'linear-gradient(90deg, var(--k-surface) 25%, var(--k-surface-muted) 50%, var(--k-surface) 75%)',
            backgroundSize: '200% 100%',
            animation: 'kf-shimmer 1.4s linear infinite',
          }}
        />
      ))}
    </div>
  )
}

function EmptyState({ title, body }: { title: string; body: string }) {
  return (
    <div className="grid place-items-center rounded-[16px] border border-dashed border-[var(--k-border-strong)] p-12 text-center">
      <p className="font-heading text-lg text-ink">{title}</p>
      <p className="mt-1 text-sm text-[var(--k-text-secondary)]">{body}</p>
    </div>
  )
}

function SearchIcon() {
  return (
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="rgb(22 33 31 / 0.4)" strokeWidth="2" aria-hidden>
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    </svg>
  )
}
