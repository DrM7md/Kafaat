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
    <div className="flex flex-col gap-8">
      <Lede total={candidates.data?.meta.total} />

      <div className="flex flex-col gap-5">
        <Toolbar
          filters={filters}
          lookups={lookups.data}
          // ★ خيارات الجنسية من **الصفوف** لا من الكتالوج: ما يحمله المسجَّلون
          //   فعلاً. تنمو مع كل موظّفٍ بجنسيةٍ جديدة، ولا تعِد بما لا نتيجةَ له.
          nationalities={candidates.data?.facets?.nationality ?? []}
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
          <div className="flex items-baseline justify-between px-1">
            <p className="text-[13px] font-semibold text-ink">
              <span className="numeric text-[17px] text-primary">
                {formatNumber(candidates.data.meta.total)}
              </span>{' '}
              كفاءة مطابقة
            </p>
          </div>

          <div className="grid gap-5 md:grid-cols-2">
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
    </div>
  )
}

/**
 * الافتتاحية — تُعرّف المكان في سطرين وتضع البحث في القلب.
 *
 * «خلف السِّتار» ليس زخرفاً: هو جوهر المنتج — الهويّة محجوبة حتى يُدفع الكشف.
 * التوهّج تحت الكلمة نعناعيّ لا ذهبيّ (الذهبيّ محجوز لفعل الكشف — Brain/02 §2).
 */
function Lede({ total }: { total?: number }) {
  return (
    <div className="text-center" style={{ animation: 'kf-rise .6s var(--k-ease) both' }}>
      {total !== undefined && (
        <span className="inline-flex items-center gap-2 rounded-full bg-mint px-3.5 py-1.5 text-[12px] font-semibold text-primary">
          <span className="relative flex size-2">
            <span
              className="absolute inline-flex size-full rounded-full bg-primary-lite opacity-60"
              style={{ animation: 'kf-ping 2.4s var(--k-ease) infinite' }}
            />
            <span className="relative inline-flex size-2 rounded-full bg-primary-lite" />
          </span>
          <span className="numeric">{formatNumber(total)}</span> كفاءة متاحة الآن
        </span>
      )}

      <h1
        className="mt-4 font-heading text-[clamp(1.6rem,3.6vw,2.4rem)] font-bold tracking-tight text-ink"
        style={{ textWrap: 'balance' }}
      >
        موظّفك القادم{' '}
        <span className="relative isolate whitespace-nowrap text-primary">
          خلف السِّتار
          <span
            aria-hidden
            className="absolute inset-x-0 bottom-1 -z-10 h-2.5 rounded-full"
            style={{ background: 'linear-gradient(90deg, var(--k-mint), rgb(228 237 235 / 0.4))' }}
          />
        </span>
      </h1>

      <p className="mx-auto mt-2 max-w-[52ch] text-[15px] text-[var(--k-text-secondary)]">
        كفاءاتٌ موثّقة بهويّةٍ محجوبة. حين تجد المناسب، ارفع السِّتارة واكشف بيانات التواصل بنقرة.
      </p>
    </div>
  )
}

function Toolbar({
  filters,
  lookups,
  nationalities,
  onChange,
  onReset,
}: {
  filters: Filters
  lookups?: Record<string, Lookup[]>
  /** الجنسيات الموجودة بين الكفاءات المعروضة — لا الكتالوج كلّه. */
  nationalities: Lookup[]
  onChange: (f: Filters) => void
  onReset: () => void
}) {
  const hasFilters = Object.values(filters).some((v) => v !== '')

  return (
    <div className="flex flex-col gap-4">
      {/* البحث — قلبُ الصفحة: حقلٌ عائمٌ محوريّ يرتفع عند التركيز */}
      <label
        className="mx-auto flex w-full max-w-2xl items-center gap-3 rounded-full bg-surface px-5 py-3 shadow-[var(--k-sh-2)] transition-shadow duration-300 focus-within:shadow-[var(--k-sh-3)]"
        style={{ animation: 'kf-rise .6s var(--k-ease) .08s both' }}
      >
        <SearchIcon />
        <input
          type="search"
          value={filters.q}
          onChange={(e) => onChange({ ...filters, q: e.target.value })}
          placeholder="ابحث بالمهارة، المجال، المسمّى…"
          className="w-full bg-transparent text-[15px] outline-none placeholder:text-[var(--k-text-muted)]"
        />
      </label>

      {/* الفلاتر — حبيباتٌ لمسية على الورق، متمركزة */}
      <div className="flex flex-wrap items-center justify-center gap-2.5">
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

        {/* حلّت محلّ قائمة الحالات: الحالات ثلاث فصارت تبويبات، والجنسيات
            قائمةٌ منسدلة — لكنّها لا تعرض إلّا ما يحمله مرشّحٌ فعلاً. */}
        <Select
          value={filters.nationality}
          onChange={(v) => onChange({ ...filters, nationality: v })}
          options={nationalities}
          placeholder="كل الجنسيات"
        />

        <input
          type="number"
          min={0}
          max={60}
          value={filters.min_experience}
          onChange={(e) => onChange({ ...filters, min_experience: e.target.value })}
          placeholder="الخبرة 5+"
          className="w-28 rounded-full bg-surface/70 px-4 py-2 text-xs font-medium shadow-[var(--k-sh-1)] outline-none transition-shadow focus:shadow-[var(--k-sh-2)]"
        />

        {hasFilters && (
          <button
            type="button"
            onClick={onReset}
            className="inline-flex cursor-pointer items-center gap-1 rounded-full px-3.5 py-2 text-xs font-medium text-primary transition-colors hover:bg-mint"
          >
            مسح الفلاتر
          </button>
        )}
      </div>

      <StatusTabs
        value={filters.status}
        options={lookups?.employment_status ?? []}
        onChange={(v) => onChange({ ...filters, status: v })}
      />
    </div>
  )
}

/**
 * الحالة الوظيفية — تبويباتٌ لا قائمة منسدلة.
 *
 * ★ هي أهمّ مِصفاةٍ للشركة وأقصرُها: «متاحٌ فوراً» تختصر نصف البحث. وقائمةٌ
 *   منسدلة تُخفي خياراتها خلف نقرة، فلا يفتحها من لا يعرف أنّ فيها ما يريد.
 *   ثلاثةُ خيارات لا تستحقّ الإخفاء — تُعرَض كلّها، فتُنقر واحدةً.
 *
 * الألوان من `lookups.meta` كشارة البطاقة نفسها: التبويبة المختارة تلبس لون
 * حالتها، فيربط النظرُ التبويبةَ بالشارة في البطاقات تحتها بلا شرح.
 */
function StatusTabs({
  value,
  options,
  onChange,
}: {
  value: string
  options: Lookup[]
  onChange: (value: string) => void
}) {
  if (options.length === 0) return null

  return (
    <div
      role="tablist"
      aria-label="الحالة الوظيفية"
      className="flex flex-wrap items-center justify-center gap-2"
    >
      <span className="ms-1 text-[11px] font-medium text-[var(--k-text-muted)]">الحالة</span>

      <Tab active={value === ''} onClick={() => onChange('')}>
        الكل
      </Tab>

      {options.map((option) => {
        const active = value === option.key

        // احتياطٌ لكل لون على حدة — كـ `StatusBadge`: حالةٌ أضافها مشرف ونسي
        // لونها تبقى تبويبةً مقروءة، لا زرّاً أبيضَ على أبيض لا يُرى اختياره.
        const bg = option.meta?.bg ?? 'var(--k-mint)'
        const color = option.meta?.color ?? 'var(--k-primary)'
        const dot = option.meta?.dot ?? '#8A9793'

        return (
          <Tab
            key={option.key}
            active={active}
            // النقر على المختارة يُلغيها: التبويبة الوحيدة المرئية للإلغاء هي
            // «الكل»، ومن نقر حالةً ثم بدا له أن يوسّع يعود إليها من مكانه.
            onClick={() => onChange(active ? '' : option.key)}
            style={active ? { background: bg, color } : undefined}
          >
            <span className="size-1.5 rounded-full" style={{ background: dot }} aria-hidden />
            {option.label}
          </Tab>
        )
      })}
    </div>
  )
}

function Tab({
  active,
  onClick,
  style,
  children,
}: {
  active: boolean
  onClick: () => void
  /** لون الحالة حين تكون مختارة. غيابه ⇒ لونُ المنصّة (تبويبة «الكل»). */
  style?: React.CSSProperties
  children: React.ReactNode
}) {
  const tone = active
    ? `border-transparent shadow-[var(--k-sh-1)] ${style === undefined ? 'bg-primary text-white' : ''}`
    : 'border-transparent bg-surface/70 text-[var(--k-text-secondary)] shadow-[var(--k-sh-1)] hover:-translate-y-px hover:text-primary'

  return (
    <button
      type="button"
      role="tab"
      aria-selected={active}
      onClick={onClick}
      style={style}
      className={`inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-medium transition-[transform,color,box-shadow] duration-200 ${tone}`}
    >
      {children}
    </button>
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
      className={`cursor-pointer rounded-full border border-transparent px-4 py-2 text-xs font-medium shadow-[var(--k-sh-1)] outline-none transition-[transform,box-shadow] duration-200 hover:-translate-y-px hover:shadow-[var(--k-sh-2)] ${
        value === ''
          ? 'bg-surface/70 text-[var(--k-text-secondary)]'
          : 'bg-mint text-primary'
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
    <div className="grid gap-5 md:grid-cols-2">
      {Array.from({ length: 4 }, (_, i) => (
        <div
          key={i}
          className="h-64 rounded-[24px] shadow-[var(--k-sh-2)]"
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
    <div className="grid place-items-center rounded-[24px] border border-dashed border-[var(--k-border-strong)] bg-surface/40 p-12 text-center">
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
