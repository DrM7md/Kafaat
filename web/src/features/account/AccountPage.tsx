import { useEffect, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { api, apiErrorMessage } from '@/shared/api/client'
import { useAuth } from '@/shared/auth/AuthProvider'
import { isCompanyAccount, type CompanyAccount, type Lookup, type Me } from '@/shared/api/types'

/**
 * «حسابي» — بيانات المستخدم عن نفسه، لا ملفّه المهني.
 *
 * الفرق ليس تسمية: ملفّ الموظّف المهني (`/profile`) **معروضٌ للشركات** ويمرّ
 * باعتماد مشرف. أمّا هذا فبيانات الدخول والتواصل — يملكها صاحبها وحده،
 * ويعدّلها بلا انتظار أحد.
 *
 * ★ حساب الشركة يزيد ببيانات الشركة، ورقمُ سجلّها **إجباري**: هو ما يُوثَّق
 *   به وجودها. وتبديلُه يُسقط التوثيق — نقولها له قبل أن يحفظ لا بعده.
 */
export function AccountPage() {
  const { me, refresh } = useAuth()

  if (me === null) return null

  return <AccountForm me={me} onSaved={refresh} />
}

type Form = {
  name: string
  email: string
  phone: string
  legal_name: string
  brand_name: string
  cr_number: string
  industry_id: number | null
  size_id: number | null
}

function AccountForm({ me, onSaved }: { me: Me; onSaved: () => Promise<void> }) {
  const company = isCompanyAccount(me.user.profile, me.user.type) ? me.user.profile : null

  const [form, setForm] = useState<Form>(() => initial(me, company))
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)

  const { data: lookups } = useQuery({
    queryKey: ['lookups'],
    queryFn: async () => (await api.get<{ lookups: Record<string, Lookup[]> }>('/lookups')).data.lookups,
    staleTime: Infinity,
    enabled: company !== null,
  })

  // النموذج يُعاد ضبطه على ما حفظه الخادم فعلاً — لا على ما أرسلناه: الخادم
  // قد يشذّب أو يرفض، فما يظهر بعد الحفظ هو الحقيقة لا أمنيتُنا.
  useEffect(() => {
    setForm(initial(me, company))
  }, [me, company])

  const save = useMutation({
    mutationFn: async () => {
      const payload: Record<string, unknown> = {
        name: form.name,
        email: form.email,
        phone: form.phone.trim() === '' ? null : form.phone.trim(),
      }

      if (company !== null) {
        payload.legal_name = form.legal_name
        payload.brand_name = form.brand_name.trim() === '' ? null : form.brand_name.trim()
        payload.cr_number = form.cr_number.trim()
        payload.industry_id = form.industry_id
        payload.size_id = form.size_id
      }

      return api.put('/me', payload)
    },
    onSuccess: async () => {
      setError(null)
      setSaved(true)
      await onSaved()
    },
    onError: (err) => {
      setSaved(false)
      setError(apiErrorMessage(err))
    },
  })

  function set<K extends keyof Form>(key: K) {
    return (value: Form[K]) => {
      setSaved(false)
      setForm((f) => ({ ...f, [key]: value }))
    }
  }

  // ★ التحذير قبل الحفظ لا بعده: الشركة الموثَّقة تفقد وصولها إلى التصفّح حتى
  //   يُعيد المشرف توثيقها، فلا يجوز أن تكتشف ذلك بعد ضغط الزرّ.
  const crChanged = company !== null && form.cr_number.trim() !== (company.cr_number ?? '')
  const willLoseVerification = crChanged && company?.verification_status === 'verified'

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6">
      <header>
        <h1 className="font-heading text-2xl text-ink">حسابي</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          بيانات الدخول والتواصل. تُحفظ فوراً، بلا اعتماد.
        </p>
      </header>

      <form
        onSubmit={(event) => {
          event.preventDefault()
          save.mutate()
        }}
        className="flex flex-col gap-5"
      >
        {error !== null && <Banner tone="error">{error}</Banner>}
        {saved && <Banner tone="ok">حُفظت بياناتك.</Banner>}

        <Card title="بيانات الحساب">
          <Field
            label={company !== null ? 'اسم المسؤول' : 'الاسم الكامل'}
            value={form.name}
            onChange={set('name')}
            required
          />
          <Field label="البريد الإلكتروني" type="email" value={form.email} onChange={set('email')} required />
          <Field
            label="رقم الهاتف"
            value={form.phone}
            onChange={set('phone')}
            hint={company === null ? 'محجوب عن الشركات حتى يدفع صاحب العمل رسم الكشف.' : undefined}
          />
        </Card>

        {company !== null && (
          <Card title="بيانات الشركة">
            <VerificationNotice status={company.verification_status} />

            <Field label="الاسم القانوني" value={form.legal_name} onChange={set('legal_name')} required />
            <Field
              label="الاسم التجاري"
              value={form.brand_name}
              onChange={set('brand_name')}
              hint="اختياري — الاسم الذي تُعرف به إن اختلف عن القانوني."
            />

            <Field
              label="رقم السجلّ التجاري"
              value={form.cr_number}
              onChange={set('cr_number')}
              required
              hint="إجباري — به يتحقّق المشرف من وجود الشركة."
            />

            {willLoseVerification && (
              <Banner tone="warn">
                تبديل رقم السجلّ يُسقط توثيق شركتك، ويعود التصفّح محجوباً حتى يتحقّق المشرف من السجلّ الجديد.
              </Banner>
            )}

            <SelectField
              label="المجال"
              value={form.industry_id}
              options={lookups?.industry ?? []}
              onChange={set('industry_id')}
            />
            <SelectField
              label="حجم الشركة"
              value={form.size_id}
              options={lookups?.company_size ?? []}
              onChange={set('size_id')}
            />
          </Card>
        )}

        <button
          type="submit"
          disabled={save.isPending}
          className="cursor-pointer self-start rounded-xl bg-primary px-6 py-2.5 text-sm font-semibold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0 disabled:opacity-50"
        >
          {save.isPending ? 'جارٍ الحفظ…' : 'حفظ التغييرات'}
        </button>
      </form>

      <PasswordCard />
    </div>
  )
}

/** تغيير كلمة المرور — نموذجٌ منفصل: مسارٌ آخر، وأخطاؤه أخطاؤه. */
function PasswordCard() {
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)

  const change = useMutation({
    mutationFn: async () => api.put('/me/password', form),
    onSuccess: () => {
      setError(null)
      setDone(true)
      setForm({ current_password: '', password: '', password_confirmation: '' })
    },
    onError: (err) => {
      setDone(false)
      setError(apiErrorMessage(err))
    },
  })

  function set(key: keyof typeof form) {
    return (value: string) => {
      setDone(false)
      setForm((f) => ({ ...f, [key]: value }))
    }
  }

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault()
        change.mutate()
      }}
      className="flex flex-col gap-5"
    >
      {error !== null && <Banner tone="error">{error}</Banner>}
      {done && <Banner tone="ok">غُيّرت كلمة المرور.</Banner>}

      <Card title="كلمة المرور">
        <Field
          label="كلمة المرور الحالية"
          type="password"
          value={form.current_password}
          onChange={set('current_password')}
          autoComplete="current-password"
          required
        />
        <Field
          label="كلمة المرور الجديدة"
          type="password"
          value={form.password}
          onChange={set('password')}
          autoComplete="new-password"
          required
        />
        <Field
          label="تأكيد كلمة المرور الجديدة"
          type="password"
          value={form.password_confirmation}
          onChange={set('password_confirmation')}
          autoComplete="new-password"
          required
        />
      </Card>

      <button
        type="submit"
        disabled={change.isPending}
        className="cursor-pointer self-start rounded-xl bg-surface px-6 py-2.5 text-sm font-semibold shadow-[var(--k-sh-1)] transition-[transform,box-shadow] duration-200 hover:-translate-y-0.5 hover:text-primary hover:shadow-[var(--k-sh-2)] disabled:opacity-50"
      >
        {change.isPending ? 'جارٍ التغيير…' : 'تغيير كلمة المرور'}
      </button>
    </form>
  )
}

function initial(me: Me, company: CompanyAccount | null): Form {
  return {
    name: me.user.name,
    email: me.user.email,
    phone: me.user.phone ?? '',
    legal_name: company?.legal_name ?? '',
    brand_name: company?.brand_name ?? '',
    cr_number: company?.cr_number ?? '',
    industry_id: company?.industry_id ?? null,
    size_id: company?.size_id ?? null,
  }
}

const VERIFICATION: Record<CompanyAccount['verification_status'], { tone: Tone; text: string }> = {
  verified: { tone: 'ok', text: 'شركتك موثَّقة.' },
  pending: { tone: 'warn', text: 'توثيق شركتك قيد المراجعة. التصفّح متاحٌ بعد التوثيق.' },
  unverified: { tone: 'warn', text: 'شركتك غير موثَّقة بعد. أكمل بياناتها ليتحقّق منها المشرف.' },
  rejected: { tone: 'error', text: 'رُفض توثيق شركتك. راجع بياناتها ثم تواصل معنا.' },
}

function VerificationNotice({ status }: { status: CompanyAccount['verification_status'] }) {
  const notice = VERIFICATION[status]

  return <Banner tone={notice.tone}>{notice.text}</Banner>
}

function Card({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="flex flex-col gap-4 rounded-[24px] bg-surface p-6 shadow-[var(--k-sh-2)]">
      <h2 className="font-heading text-base text-ink">{title}</h2>
      {children}
    </section>
  )
}

type Tone = 'ok' | 'warn' | 'error'

const TONES: Record<Tone, string> = {
  ok: 'border-[rgb(30_122_68/0.3)] bg-[#E7F3EA] text-[#1E7A44]',
  warn: 'border-[rgb(180_134_59/0.35)] bg-gold-tint text-[#96682A]',
  error: 'border-[rgb(200_60_60/0.3)] bg-[rgb(200_60_60/0.08)] text-[#8a2b2b]',
}

function Banner({ tone, children }: { tone: Tone; children: React.ReactNode }) {
  return <p className={`rounded-xl border p-3 text-[12.5px] leading-6 ${TONES[tone]}`}>{children}</p>
}

function Field({
  label,
  type = 'text',
  value,
  onChange,
  required = false,
  autoComplete,
  hint,
}: {
  label: string
  type?: string
  value: string
  onChange: (value: string) => void
  required?: boolean
  autoComplete?: string
  hint?: string
}) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-medium text-ink">
        {label}
        {required && <span className="text-primary"> *</span>}
      </span>

      <input
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        required={required}
        autoComplete={autoComplete}
        className="rounded-xl border border-[var(--k-border-strong)] bg-surface px-3.5 py-2.5 text-sm outline-none focus:border-primary"
      />

      {hint !== undefined && <span className="text-[11px] text-[var(--k-text-muted)]">{hint}</span>}
    </label>
  )
}

function SelectField({
  label,
  value,
  options,
  onChange,
}: {
  label: string
  value: number | null
  options: Lookup[]
  onChange: (value: number | null) => void
}) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-medium text-ink">{label}</span>
      <select
        value={value ?? ''}
        onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
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
