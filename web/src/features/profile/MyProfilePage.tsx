import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiErrorMessage } from '@/shared/api/client'
import type { Lookup } from '@/shared/api/types'
import { Field } from '@/features/auth/AuthShell'

type ApprovalStatus = 'draft' | 'pending' | 'approved' | 'rejected'

type Profile = {
  ref: string
  title: string
  approval_status: ApprovalStatus
  rejection_reason: string | null
  field_id: number | null
  years_experience: number
  city_id: number | null
  employment_status_id: number | null
  expected_salary_min: number | null
  expected_salary_max: number | null
  summary: string | null
  current_employer_name: string | null
  visibility: 'public' | 'hidden'
  completeness: number
  published_at: string | null
  skills: { id: number; name: string }[]
}

/**
 * نموذج التحرير: المهارات نصٌّ مفصولٌ بفواصل، لا مصفوفة كائنات. ويضمّ حقولاً
 * قابلة للتحرير يقبلها الـ API لكنّها ليست في استجابة العرض بعد.
 */
type ProfileForm = Partial<Omit<Profile, 'skills'>> & {
  skills?: string
  notice_period_clause?: string | null
  penalty_amount?: number | null
  current_employer_company_id?: number | null
}

/** الحقول التي يقبلها PUT /profile — كل ما عداها يملكه الخادم. */
const EDITABLE_KEYS = [
  'title',
  'field_id',
  'years_experience',
  'city_id',
  'employment_status_id',
  'expected_salary_min',
  'expected_salary_max',
  'summary',
  'notice_period_clause',
  'penalty_amount',
  'current_employer_company_id',
  'current_employer_name',
  'visibility',
] as const

export function MyProfilePage() {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<ProfileForm>({})
  const [message, setMessage] = useState<string | null>(null)

  const { data: profile, isPending, isError } = useQuery({
    queryKey: ['profile'],
    queryFn: async () => (await api.get<{ profile: Profile }>('/profile')).data.profile,
  })

  const { data: lookups } = useQuery({
    queryKey: ['lookups'],
    queryFn: async () => (await api.get<{ lookups: Record<string, Lookup[]> }>('/lookups')).data.lookups,
    staleTime: Infinity,
  })

  useEffect(() => {
    if (profile !== undefined) {
      setForm({ ...profile, skills: profile.skills.map((s) => s.name).join('، ') })
    }
  }, [profile])

  const save = useMutation({
    mutationFn: async () => {
      // نُرسل الحقول القابلة للتحرير فقط. إرسال `{...form}` كان يبعث حقولاً
      // يملكها الخادم (ref/completeness/published_at/approval_status/visibility…).
      const payload: Record<string, unknown> = {
        skills: form.skills?.split('،').map((s) => s.trim()).filter(Boolean) ?? [],
      }
      for (const key of EDITABLE_KEYS) {
        if (form[key] !== undefined) payload[key] = form[key]
      }
      return api.put('/profile', payload)
    },
    onSuccess: async () => {
      setMessage('حُفظ ملفّك.')
      await queryClient.invalidateQueries({ queryKey: ['profile'] })
    },
  })

  const publish = useMutation({
    mutationFn: async (action: 'publish' | 'unpublish') => api.post(`/profile/${action}`),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['profile'] })
    },
  })

  if (isPending) {
    return <p className="py-16 text-center text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>
  }

  if (isError || profile === undefined) {
    return (
      <div className="grid place-items-center rounded-[16px] border border-dashed border-[var(--k-border-strong)] p-12 text-center">
        <p className="font-heading text-lg text-ink">تعذّر تحميل ملفّك</p>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">تأكّد من الاتصال ثم أعد المحاولة.</p>
      </div>
    )
  }

  const submitted = profile.published_at !== null

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6">
      <header>
        <h1 className="font-heading text-2xl text-ink">ملفّي المهني</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          اسمك وبيانات تواصلك <strong>محجوبة</strong> عن الشركات حتى تدفع رسم الكشف.
        </p>
      </header>

      <ApprovalNotice status={profile.approval_status} reason={profile.rejection_reason} />

      <div className="rounded-[20px] border border-[var(--k-border)] bg-surface p-5">
        <div className="mb-4 flex items-center justify-between">
          <div>
            <p className="text-[13px] font-semibold text-ink">اكتمال الملفّ</p>
            <p className="numeric text-2xl font-bold text-primary">{profile.completeness}%</p>
          </div>

          <button
            type="button"
            disabled={publish.isPending}
            onClick={() => publish.mutate(submitted ? 'unpublish' : 'publish')}
            className={`cursor-pointer rounded-xl px-4 py-2.5 text-sm font-semibold disabled:opacity-50 ${
              submitted ? 'bg-surface-muted text-ink' : 'bg-primary text-white'
            }`}
          >
            {submitted ? 'سحب الطلب' : 'تقديم للاعتماد'}
          </button>
        </div>

        <div className="h-2 overflow-hidden rounded-full bg-surface-muted">
          <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${profile.completeness}%` }} />
        </div>

        {publish.isError && (
          <p className="mt-3 text-xs text-[#96682A]">{apiErrorMessage(publish.error)}</p>
        )}
      </div>

      <form
        onSubmit={(e) => {
          e.preventDefault()
          setMessage(null)
          save.mutate()
        }}
        className="flex flex-col gap-4 rounded-[20px] border border-[var(--k-border)] bg-surface p-5"
      >
        {message !== null && (
          <p className="rounded-xl bg-mint p-3 text-sm text-primary">{message}</p>
        )}
        {save.isError && (
          <p className="rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
            {apiErrorMessage(save.error)}
          </p>
        )}

        <Field label="المسمّى الوظيفي" value={form.title ?? ''} onChange={(v) => setForm({ ...form, title: v })} />

        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField
            label="المجال"
            value={form.field_id}
            options={lookups?.field ?? []}
            onChange={(v) => setForm({ ...form, field_id: v })}
          />
          <SelectField
            label="المدينة"
            value={form.city_id}
            options={lookups?.city ?? []}
            onChange={(v) => setForm({ ...form, city_id: v })}
          />
        </div>

        <SelectField
          label="الحالة الوظيفية"
          value={form.employment_status_id}
          options={lookups?.employment_status ?? []}
          onChange={(v) => setForm({ ...form, employment_status_id: v })}
        />

        <Field
          label="سنوات الخبرة"
          type="number"
          value={String(form.years_experience ?? 0)}
          onChange={(v) => setForm({ ...form, years_experience: Number(v) })}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="الراتب المتوقّع (من)"
            type="number"
            value={String(form.expected_salary_min ?? '')}
            onChange={(v) => setForm({ ...form, expected_salary_min: v === '' ? null : Number(v) })}
          />
          <Field
            label="الراتب المتوقّع (إلى)"
            type="number"
            value={String(form.expected_salary_max ?? '')}
            onChange={(v) => setForm({ ...form, expected_salary_max: v === '' ? null : Number(v) })}
          />
        </div>

        <Field
          label="المهارات"
          value={form.skills ?? ''}
          onChange={(v) => setForm({ ...form, skills: v })}
          hint="افصل بينها بفاصلة عربية «،»"
        />

        <label className="flex flex-col gap-1.5">
          <span className="text-[13px] font-medium text-ink">نبذة</span>
          <textarea
            rows={4}
            value={form.summary ?? ''}
            onChange={(e) => setForm({ ...form, summary: e.target.value })}
            className="rounded-xl border border-[var(--k-border-strong)] bg-surface px-3.5 py-2.5 text-sm outline-none focus:border-primary"
          />
        </label>

        <Field
          label="جهة العمل الحالية"
          value={form.current_employer_name ?? ''}
          onChange={(v) => setForm({ ...form, current_employer_name: v })}
          hint="نستخدم هذا لنبذل جهدنا في منع شركتك من رؤية ملفّك — لا لعرضه."
        />

        <button
          type="submit"
          disabled={save.isPending}
          className="mt-2 cursor-pointer rounded-[14px] bg-primary px-4 py-3 text-sm font-bold text-white hover:brightness-110 disabled:opacity-60"
        >
          {save.isPending ? 'جارٍ الحفظ…' : 'حفظ'}
        </button>
      </form>
    </div>
  )
}

/** الموظّف يعرف أين ملفّه من دورة الاعتماد — لا يُترك في العماء. */
function ApprovalNotice({ status, reason }: { status: ApprovalStatus; reason: string | null }) {
  if (status === 'draft') return null

  const styles = {
    pending: {
      border: 'rgb(180 134 59 / 0.35)',
      bg: 'var(--k-gold-tint)',
      color: '#96682A',
      title: 'طلبك قيد المراجعة',
      body: 'يراجع المشرف ملفّك. لن يظهر لأي شركة قبل اعتماده.',
    },
    approved: {
      border: 'rgb(47 163 92 / 0.35)',
      bg: '#E7F3EA',
      color: '#1E7A44',
      title: 'ملفّك معتمَد وظاهر للشركات',
      body: 'هويّتك ما زالت محجوبة حتى تدفع شركةٌ رسم الكشف.',
    },
    rejected: {
      border: 'rgb(200 60 60 / 0.35)',
      bg: 'rgb(200 60 60 / 0.06)',
      color: '#8a2b2b',
      title: 'رُفض طلبك',
      body: reason ?? 'راجع ملفّك ثم قدّمه من جديد.',
    },
  }[status]

  return (
    <div
      className="rounded-xl border p-3.5 text-[13px]"
      style={{ borderColor: styles.border, background: styles.bg, color: styles.color }}
    >
      <strong>{styles.title}</strong> — {styles.body}
    </div>
  )
}

function SelectField({
  label,
  value,
  options,
  onChange,
}: {
  label: string
  value: number | null | undefined
  options: Lookup[]
  onChange: (v: number | null) => void
}) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-medium text-ink">{label}</span>
      <select
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
        className="cursor-pointer rounded-xl border border-[var(--k-border-strong)] bg-surface px-3.5 py-2.5 text-sm outline-none"
      >
        <option value="">—</option>
        {options.map((option) => (
          <option key={option.key} value={option.id}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  )
}
