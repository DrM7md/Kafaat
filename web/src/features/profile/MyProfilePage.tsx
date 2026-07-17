import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiErrorMessage } from '@/shared/api/client'
import type { Lookup } from '@/shared/api/types'
import { Field } from '@/features/auth/AuthShell'
import { Combobox, type ComboValue } from '@/shared/ui/Combobox'
import { MoneyField } from '@/shared/ui/MoneyField'
import { currencyLabel } from '@/shared/lib/format'
import { Attachments, type Attachment } from './Attachments'
import { ReadinessRefreshNotice, ReadinessSlider } from './ReadinessSlider'

type ApprovalStatus = 'draft' | 'pending' | 'approved' | 'rejected'

type Profile = {
  ref: string
  title: string
  approval_status: ApprovalStatus
  rejection_reason: string | null
  field_id: number | null
  years_experience: number
  country_id: number | null
  nationality_id: number | null
  city_id: number | null
  employment_status_id: number | null

  /** 0–100. الحالة الوظيفية تُشتقّ منه في **الخادم** — لا تُرسَل من هنا. */
  readiness: number | null

  expected_salary_min: number | null
  expected_salary_max: number | null
  currency: string | null
  summary: string | null
  current_employer_company_id: number | null
  current_employer_name: string | null
  visibility: 'public' | 'hidden'
  completeness: number
  published_at: string | null
  skills: { id: number; name: string }[]
  attachments: Attachment[]
}

/** أحكام الخادم على نضارة المؤشّر — عتبتاها إعدادان يملكهما المشرف. */
type ReadinessState = {
  age_days: number | null
  needs_confirmation: boolean
  stale: boolean
}

/** شركةٌ مسجَّلة — بشكل `Lookup` نفسه، فيقبلها الـ Combobox بلا مكوّنٍ جديد. */
type Employer = { id: number; key: string; label: string }

/**
 * نموذج التحرير: المهارات نصٌّ مفصولٌ بفواصل، لا مصفوفة كائنات. ويضمّ حقولاً
 * قابلة للتحرير يقبلها الـ API لكنّها ليست في استجابة العرض بعد.
 */
type ProfileForm = Partial<Omit<Profile, 'skills' | 'attachments'>> & {
  skills?: string
  notice_period_clause?: string | null
  penalty_amount?: number | null
  current_employer_company_id?: number | null

  // نصّ «أخرى»: يُرسَل إلى الخادم فيصير اقتراحاً معلَّقاً.
  field_other?: string
  city_other?: string
  nationality_other?: string
}

/** الحقول التي يقبلها PUT /profile — كل ما عداها يملكه الخادم. */
const EDITABLE_KEYS = [
  'title',
  'field_id',
  'field_other',
  'years_experience',
  'country_id',
  'nationality_id',
  'city_id',
  'city_other',
  'nationality_other',

  // ★ `employment_status_id` **ليست هنا** عمداً: الخادم يشتقّها من `readiness`.
  //   لو أرسلناها لأمكن أن تصل نسبة 95 وحالة «مستقرّ» معاً — فيظهر مستقرّاً في
  //   الشارة، وفي مقدّمة فرز «الراغبين في الانتقال» في آنٍ واحد.
  'readiness',

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

  // التذكير يُخفى لهذه الجلسة وحدها: من أغلقه ولم يُعدّل حالته يجب أن يراه
  // غداً من جديد — الحالة البائتة لا تُصلحها نقرةُ «سأُعدّلها».
  const [refreshDismissed, setRefreshDismissed] = useState(false)

  const { data, isPending, isError } = useQuery({
    queryKey: ['profile'],
    queryFn: async () =>
      (await api.get<{ profile: Profile; readiness_state: ReadinessState }>('/profile')).data,
  })

  const profile = data?.profile
  const readinessState = data?.readiness_state

  const { data: lookups } = useQuery({
    queryKey: ['lookups'],
    queryFn: async () => (await api.get<{ lookups: Record<string, Lookup[]> }>('/lookups')).data.lookups,
    staleTime: Infinity,
  })

  // الشركات المسجَّلة — منها يختار الموظّف جهةَ عمله فتُحجَب عنه.
  const { data: employers } = useQuery({
    queryKey: ['employers'],
    queryFn: async () => (await api.get<{ employers: Employer[] }>('/employers')).data.employers,
    staleTime: Infinity,
  })

  const countries = lookups?.country ?? []

  // الدولة الافتراضية أوّلُ دولةٍ نشطة في القائمة — اليوم قطر وحدها. لا نكتب
  // «قطر» هنا: فتحُ الإمارات غداً يجب ألّا يمسّ سطراً من هذا الملفّ.
  const defaultCountryId = countries[0]?.id ?? null
  const countryId = form.country_id ?? defaultCountryId

  const country = countries.find((c) => c.id === countryId) ?? null
  const currency = currencyLabel(country?.meta?.currency ?? profile?.currency)

  // المدن تُرشَّح بالدولة. مدينةٌ بلا أبٍ (اقتراحٌ قديم سابق للدول) تبقى ظاهرة
  // بدل أن تختفي من تحت صاحبها.
  const cities = useMemo(
    () => (lookups?.city ?? []).filter((c) => c.parent_id == null || c.parent_id === countryId),
    [lookups?.city, countryId],
  )

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

      // الدولة الافتراضية معروضة في الواجهة، فيجب أن تُحفظ كذلك: بلا هذا يرى
      // الموظّف «قطر» مختارةً ويحفظ، فيبقى `country_id` فارغاً — ومعه العملة.
      payload.country_id = countryId

      return api.put('/profile', payload)
    },
    onSuccess: async () => {
      setMessage('حُفظ ملفّك.')
      await queryClient.invalidateQueries({ queryKey: ['profile'] })
    },
  })

  // ★ نقرةٌ واحدة تُعيد ختم الحالة بتاريخ اليوم — دون فتح النموذج كلّه.
  const confirmReadiness = useMutation({
    mutationFn: async () => api.post('/profile/readiness/confirm'),
    onSuccess: async () => {
      setMessage('جُدِّدت حالتك. تراها الشركات محدَّثةً اليوم.')
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
      <div className="grid place-items-center rounded-[20px] border border-dashed border-[var(--k-border-strong)] p-12 text-center">
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

      {/* ★ حالةٌ شاخت. لا تُعرض إلّا لمن ضبط مؤشّره أصلاً — من لم يضبطه قطّ
          لا شيء عنده ليُجدّده، ولا معنى لأن نُلحّ عليه. */}
      {readinessState !== undefined &&
        readinessState.needs_confirmation &&
        readinessState.age_days !== null &&
        !refreshDismissed && (
          <ReadinessRefreshNotice
            ageDays={readinessState.age_days}
            stale={readinessState.stale}
            pending={confirmReadiness.isPending}
            onConfirm={() => confirmReadiness.mutate()}
            onDismiss={() => setRefreshDismissed(true)}
          />
        )}

      <div className="rounded-[24px] bg-surface p-5 shadow-[var(--k-sh-2)]">
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
              submitted ? 'bg-surface-muted text-ink' : 'bg-primary text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0'
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
        className="flex flex-col gap-4 rounded-[24px] bg-surface p-5 shadow-[var(--k-sh-2)]"
      >
        {message !== null && (
          <p className="rounded-xl bg-mint p-3 text-sm text-primary">{message}</p>
        )}
        {save.isError && (
          <p className="rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
            {apiErrorMessage(save.error)}
          </p>
        )}

        <Field
          label="المسمّى الوظيفي الحالي"
          value={form.title ?? ''}
          onChange={(v) => setForm({ ...form, title: v })}
          hint="مسمّاك اليوم — يظهر في التصفّح ولا يُحجب."
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <Combobox
            label="المجال"
            value={{ id: form.field_id ?? null, other: form.field_other ?? '' }}
            options={lookups?.field ?? []}
            onChange={(v: ComboValue) => setForm({ ...form, field_id: v.id, field_other: v.other })}
            placeholder="ابحث عن مجالك…"
            otherLabel="أخرى — أضِف مجالاً"
          />

          <SelectField
            label="الدولة"
            value={countryId}
            options={countries}
            onChange={(v) =>
              // تبديل الدولة يُسقط المدينة: مدينةُ قطر لا تصلح لملفٍّ إماراتي.
              setForm({ ...form, country_id: v, city_id: null, city_other: '' })
            }
          />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Combobox
            label="المدينة"
            value={{ id: form.city_id ?? null, other: form.city_other ?? '' }}
            options={cities}
            onChange={(v: ComboValue) => setForm({ ...form, city_id: v.id, city_other: v.other })}
            placeholder="ابحث عن مدينتك…"
            otherLabel="أخرى — أضِف مدينة"
            disabled={countryId === null}
            hint={country !== null ? `مدن ${country.label}.` : undefined}
          />

          {/* الجنسية غير الدولة: الدولة أين تعمل، والجنسية من أنت. تُصفّي بها
              الشركةُ بحثها (تأشيرة، توطين) — فتُعرض في البطاقة ولا تُحجب.

              ★ `Combobox` لا `SelectField`: الكتالوج مئةٌ ونصف. قائمةٌ عادية
                تُمرَّر بالعجلة حتى يملّ صاحبها، وهذه يُكتب فيها حرفان فتنحصر
                في سطر. والبحث يُطبّع الهمزات والتاء المربوطة، فمن كتب «هندي»
                أو «هندى» يجدها. */}
          <Combobox
            label="الجنسية"
            value={{ id: form.nationality_id ?? null, other: form.nationality_other ?? '' }}
            options={lookups?.nationality ?? []}
            onChange={(v: ComboValue) =>
              setForm({ ...form, nationality_id: v.id, nationality_other: v.other })
            }
            placeholder="ابحث عن جنسيتك…"
            otherLabel="أخرى — أضِف جنسية"
          />
        </div>

        {/* ★ شريطٌ لا قائمة: «راغب في ترك عمله» اعترافٌ لا يُقال، والتدرّجُ
            يُقال. راجع ReadinessSlider. */}
        <ReadinessSlider
          value={form.readiness ?? null}
          statuses={lookups?.employment_status ?? []}
          onChange={(v) => setForm({ ...form, readiness: v })}
        />

        <Field
          label="سنوات الخبرة"
          type="number"
          value={String(form.years_experience ?? 0)}
          onChange={(v) => setForm({ ...form, years_experience: Number(v) })}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <MoneyField
            label="الراتب المتوقّع (من)"
            value={form.expected_salary_min ?? null}
            currency={currency}
            onChange={(v) => setForm({ ...form, expected_salary_min: v })}
          />
          <MoneyField
            label="الراتب المتوقّع (إلى)"
            value={form.expected_salary_max ?? null}
            currency={currency}
            onChange={(v) => setForm({ ...form, expected_salary_max: v })}
            hint="النطاق يظهر للشركات — لا يُحجب."
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

        {/* ★★ هذا الحقل هو ما يُشغّل «عمى صاحب العمل».
            اختيارُ الشركة من القائمة يربط الملفّ بها، فتختفي عنها: لا في
            التصفّح، ولا بالرابط المباشر، ولا **حتى لو دفعت رسم الكشف**.
            ومن لم يجد شركته يكتب اسمها، فنُطابقه بها يوم تسجّل. */}
        <Combobox
          label="جهة العمل الحالية"
          value={{
            id: form.current_employer_company_id ?? null,
            other: form.current_employer_name ?? '',
          }}
          options={employers ?? []}
          onChange={(v: ComboValue) =>
            setForm({
              ...form,
              current_employer_company_id: v.id,
              current_employer_name: v.other === '' ? null : v.other,
            })
          }
          placeholder="ابحث عن شركتك…"
          otherLabel="شركتي غير مسجّلة — اكتب اسمها"
          otherHint="سنُطابقها بشركتك يوم تسجّل في المنصّة، فتُحجَب عنها تلقائياً."
          hint="نستخدم هذا لنحجب ملفّك عن شركتك — لا لنعرضه لأحد."
        />

        <button
          type="submit"
          disabled={save.isPending}
          className="mt-2 cursor-pointer rounded-[14px] bg-primary px-4 py-3 text-sm font-bold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0 disabled:opacity-60"
        >
          {save.isPending ? 'جارٍ الحفظ…' : 'حفظ'}
        </button>
      </form>

      {/* المرفقات خارج النموذج: ترفع فوراً بطلبها الخاصّ، ولا تنتظر «حفظ». */}
      <Attachments attachments={profile.attachments} />
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
