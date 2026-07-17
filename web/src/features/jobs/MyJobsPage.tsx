import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { apiErrorMessage } from '@/shared/api/client'
import type { Job, JobSlots, Lookup } from '@/shared/api/types'
import { Field } from '@/features/auth/AuthShell'
import { MoneyField } from '@/shared/ui/MoneyField'
import { Combobox, type ComboValue } from '@/shared/ui/Combobox'
import { formatMoney, formatNumber } from '@/shared/lib/format'
import { useBillingUnderDevelopment } from '@/shared/hooks/usePublicSettings'
import { api } from '@/shared/api/client'
import { myJobsQuery, usePurchaseSlot, useMyJobMutations } from './queries'

/**
 * وظائف الشركة — نشرُها وإدارتها ضمن الحصّة.
 *
 * ★ الحصّة تُعدّ **المفتوحة** وحدها: إغلاق وظيفةٍ يحرّر خانتها. الصفحة تقول
 *   ذلك صراحةً، فيعرف من نفدت حصّتُه أنّ أمامه بابين لا باباً واحداً — ينظّف
 *   لوحته، أو يشتري خانة.
 */
export function MyJobsPage() {
  const { data, isPending } = useQuery(myJobsQuery())
  const [creating, setCreating] = useState(false)

  const slots = data?.slots
  const jobs = data?.data ?? []

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-5">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="font-heading text-2xl text-ink">وظائفي</h1>
          <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
            انشر متطلّباتك — ويصلك من يهتمّ بها، <strong>محجوب الهوية</strong> حتى تكشفه.
          </p>
        </div>

        <button
          type="button"
          onClick={() => setCreating((v) => !v)}
          className="cursor-pointer rounded-xl bg-primary px-4 py-2.5 text-[13px] font-semibold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0"
        >
          {creating ? 'إلغاء' : 'وظيفة جديدة'}
        </button>
      </header>

      {slots !== undefined && <SlotBar slots={slots} />}

      {creating && <JobForm onDone={() => setCreating(false)} />}

      {isPending ? (
        <p className="py-16 text-center text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>
      ) : jobs.length === 0 ? (
        <div className="grid place-items-center rounded-[24px] border border-dashed border-[var(--k-border-strong)] p-14 text-center">
          <p className="font-heading text-base text-ink">لا وظائف بعد</p>
          <p className="mt-1 text-[13px] text-[var(--k-text-secondary)]">
            انشر أوّل متطلّب — «مطلوب مهندس كهربائي».
          </p>
        </div>
      ) : (
        <ul className="flex flex-col gap-3">
          {jobs.map((job) => (
            <li key={job.ref}>
              <MyJobRow job={job} />
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function SlotBar({ slots }: { slots: JobSlots }) {
  const purchase = usePurchaseSlot()
  const billingUnderDevelopment = useBillingUnderDevelopment()

  const exhausted = slots.remaining === 0

  return (
    <section
      className={`rounded-[24px] p-5 ${
        exhausted
          ? 'border border-[rgb(180_134_59/0.35)] bg-gold-tint'
          : 'bg-surface shadow-[var(--k-sh-2)]'
      }`}
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className={`text-[13px] font-semibold ${exhausted ? 'text-[#96682A]' : 'text-ink'}`}>
            <span className="numeric">{formatNumber(slots.used)}</span> من{' '}
            <span className="numeric">{formatNumber(slots.total)}</span> وظيفة مفتوحة
          </p>
          <p className={`mt-1 text-[12px] ${exhausted ? 'text-[#96682A]' : 'text-[var(--k-text-muted)]'}`}>
            {exhausted
              ? 'استنفدتَ حصّتك. أغلق وظيفةً لتحرّر خانتها، أو اشترِ خانة إضافية.'
              : `تبقّى لك ${formatNumber(slots.remaining)} — وإغلاق أي وظيفة يحرّر خانتها.`}
          </p>
        </div>

        {exhausted && (
          <button
            type="button"
            disabled={purchase.isPending || billingUnderDevelopment}
            onClick={() => purchase.mutate()}
            className="shrink-0 cursor-pointer rounded-xl px-4 py-2.5 text-[13px] font-bold text-white disabled:cursor-not-allowed disabled:opacity-60"
            style={{ background: 'var(--k-gold)', boxShadow: 'var(--k-shadow-gold)' }}
          >
            {purchase.isPending ? 'جارٍ…' : `خانة إضافية · ${formatMoney(slots.extra_fee, 'QAR')}`}
          </button>
        )}
      </div>

      {slots.credit > 0 && (
        <p className="mt-3 rounded-xl bg-surface p-3 text-[12px] text-primary">
          لديك <span className="numeric font-semibold">{formatNumber(slots.credit)}</span> خانة مدفوعة لم
          تُستعمل بعد — تُستهلك تلقائياً عند فتح وظيفتك التالية.
        </p>
      )}

      {billingUnderDevelopment && exhausted && (
        <p className="mt-3 text-[11px] text-[#96682A]">
          الدفع قيد التطوير — سيُفعَّل عند ربط بوّابة الدفع.
        </p>
      )}

      {purchase.isError && (
        <p className="mt-3 text-[12px] text-[#96682A]">{apiErrorMessage(purchase.error)}</p>
      )}
    </section>
  )
}

function MyJobRow({ job }: { job: Job }) {
  const { open, close, remove } = useMyJobMutations()

  const isOpen = job.status === 'open'

  return (
    <article className="rounded-[20px] bg-surface p-4 shadow-[var(--k-sh-2)]">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <p className="text-[14px] font-semibold text-ink">{job.title}</p>
            <StatusChip status={job.status} />
            {job.is_paid_slot === true && (
              <span className="rounded-full bg-gold-tint px-2 py-0.5 text-[10px] font-medium text-[#96682A]">
                خانة مدفوعة
              </span>
            )}
          </div>

          <p className="mt-1 line-clamp-1 text-[12px] text-[var(--k-text-secondary)]">{job.description}</p>
        </div>

        <div className="flex shrink-0 items-center gap-2">
          {isOpen ? (
            <button
              type="button"
              disabled={close.isPending}
              onClick={() => close.mutate(job.ref)}
              className="cursor-pointer rounded-lg border border-[var(--k-border-strong)] px-3 py-1.5 text-[12px] text-[var(--k-text-secondary)] hover:border-primary disabled:opacity-50"
            >
              إغلاق
            </button>
          ) : (
            <button
              type="button"
              disabled={open.isPending}
              onClick={() => open.mutate(job.ref)}
              className="cursor-pointer rounded-lg bg-primary px-3 py-1.5 text-[12px] font-semibold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0 disabled:opacity-50"
            >
              فتح
            </button>
          )}

          {job.status === 'draft' && (
            <button
              type="button"
              disabled={remove.isPending}
              onClick={() => remove.mutate(job.ref)}
              className="cursor-pointer text-[12px] text-[#8a2b2b] hover:underline disabled:opacity-50"
            >
              حذف
            </button>
          )}
        </div>
      </div>

      {open.isError && (
        <p className="mt-2 rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-2.5 text-[12px] text-[#96682A]">
          {apiErrorMessage(open.error)}
        </p>
      )}

      <div className="mt-3 flex items-center justify-between border-t border-[var(--k-divider)] pt-3">
        <span className="numeric text-[12px] text-[var(--k-text-secondary)]">
          {formatNumber(job.interests_count ?? 0)} مهتمّ
          {(job.new_interests_count ?? 0) > 0 && (
            <span className="mr-2 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white">
              {formatNumber(job.new_interests_count ?? 0)} جديد
            </span>
          )}
        </span>

        {(job.interests_count ?? 0) > 0 && (
          <Link to={`/jobs/${job.ref}/interested`} className="text-[12px] font-semibold">
            عرض المهتمّين ←
          </Link>
        )}
      </div>
    </article>
  )
}

function StatusChip({ status }: { status: Job['status'] }) {
  const tone = {
    open: { label: 'مفتوحة', bg: '#E7F3EA', color: '#1E7A44' },
    draft: { label: 'مسودّة', bg: 'var(--k-surface-muted)', color: 'var(--k-text-secondary)' },
    closed: { label: 'مغلقة', bg: '#ECEFEE', color: '#566460' },
  }[status]

  return (
    <span
      className="rounded-full px-2 py-0.5 text-[10px] font-medium"
      style={{ background: tone.bg, color: tone.color }}
    >
      {tone.label}
    </span>
  )
}

/** نموذج نشر وظيفة. تُنشأ **مسودّة** — الفتح فعلٌ منفصل يشغل خانة. */
function JobForm({ onDone }: { onDone: () => void }) {
  const { create } = useMyJobMutations()

  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [field, setField] = useState<ComboValue>({ id: null, other: '' })
  const [minExperience, setMinExperience] = useState('0')
  const [salaryMin, setSalaryMin] = useState<number | null>(null)
  const [salaryMax, setSalaryMax] = useState<number | null>(null)

  const { data: lookups } = useQuery({
    queryKey: ['lookups'],
    queryFn: async () => (await api.get<{ lookups: Record<string, Lookup[]> }>('/lookups')).data.lookups,
    staleTime: Infinity,
  })

  function submit(event: React.FormEvent) {
    event.preventDefault()

    create.mutate(
      {
        title,
        description,
        field_id: field.id,
        min_experience: Number(minExperience) || 0,
        salary_min: salaryMin,
        salary_max: salaryMax,
      },
      { onSuccess: onDone },
    )
  }

  return (
    <form
      onSubmit={submit}
      className="flex flex-col gap-4 rounded-[24px] bg-surface p-5 shadow-[var(--k-sh-2)]"
    >
      {create.isError && (
        <p className="rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
          {apiErrorMessage(create.error)}
        </p>
      )}

      <Field
        label="المسمّى المطلوب"
        value={title}
        onChange={setTitle}
        required
        hint="مثال: مهندس كهربائي"
      />

      <label className="flex flex-col gap-1.5">
        <span className="text-[13px] font-medium text-ink">الوصف والمتطلّبات</span>
        <textarea
          rows={4}
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          required
          className="rounded-xl border border-[var(--k-border-strong)] bg-surface px-3.5 py-2.5 text-sm outline-none focus:border-primary"
        />
      </label>

      <div className="grid gap-4 sm:grid-cols-2">
        <Combobox
          label="المجال"
          value={field}
          options={lookups?.field ?? []}
          onChange={setField}
          placeholder="اختر المجال…"
        />

        <Field
          label="أقلّ خبرة مطلوبة (سنوات)"
          type="number"
          value={minExperience}
          onChange={setMinExperience}
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <MoneyField label="الراتب (من)" value={salaryMin} currency="ر.ق" onChange={setSalaryMin} />
        <MoneyField label="الراتب (إلى)" value={salaryMax} currency="ر.ق" onChange={setSalaryMax} />
      </div>

      <button
        type="submit"
        disabled={create.isPending}
        className="mt-1 cursor-pointer rounded-[14px] bg-primary px-4 py-3 text-sm font-bold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0 disabled:opacity-60"
      >
        {create.isPending ? 'جارٍ الحفظ…' : 'حفظ كمسودّة'}
      </button>

      <p className="text-center text-[11px] text-[var(--k-text-muted)]">
        تُحفظ مسودّةً. فتحُها لاحقاً هو ما يُظهرها للباحثين ويشغل خانةً من حصّتك.
      </p>
    </form>
  )
}
