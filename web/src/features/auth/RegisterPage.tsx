import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { api, apiErrorMessage } from '@/shared/api/client'
import { useAuth } from '@/shared/auth/AuthProvider'
import { AuthShell, Checkbox, Field, SubmitButton } from './AuthShell'

type Kind = 'employee' | 'company'

export function RegisterPage({ kind }: { kind: Kind }) {
  const isEmployee = kind === 'employee'

  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    title: '',
    legal_name: '',
    cr_number: '',
  })

  // قانون رقم 13: موافقتان منفصلتان، ولا خانة مُعبّأة مسبقاً.
  const [acceptTerms, setAcceptTerms] = useState(false)
  const [acceptPrivacy, setAcceptPrivacy] = useState(false)

  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)

  const { refresh } = useAuth()
  const navigate = useNavigate()

  function set(key: keyof typeof form) {
    return (value: string) => setForm((f) => ({ ...f, [key]: value }))
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    setPending(true)
    setError(null)

    const payload = {
      name: form.name,
      email: form.email,
      phone: form.phone === '' ? null : form.phone,
      password: form.password,
      password_confirmation: form.password_confirmation,
      accept_terms: acceptTerms,
      accept_privacy: acceptPrivacy,
      ...(isEmployee ? { title: form.title } : { legal_name: form.legal_name, cr_number: form.cr_number }),
    }

    try {
      await api.post(`/auth/register/${kind}`, payload)
      await refresh()
      void navigate('/')
    } catch (err) {
      setError(apiErrorMessage(err))
    } finally {
      setPending(false)
    }
  }

  return (
    <AuthShell
      title={isEmployee ? 'تسجيل باحث عن عمل' : 'تسجيل شركة'}
      subtitle={
        isEmployee
          ? 'ملفّك مجهول الهوية حتى تدفع شركةٌ رسم الكشف.'
          : 'تصفّح الكفاءات، وادفع عند كشف من يناسبك.'
      }
    >
      <form onSubmit={(e) => void handleSubmit(e)} className="flex flex-col gap-4">
        {error !== null && (
          <p className="rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
            {error}
          </p>
        )}

        <Field label={isEmployee ? 'الاسم الكامل' : 'اسم المسؤول'} value={form.name} onChange={set('name')} required />

        {isEmployee ? (
          <Field
            label="المسمّى الوظيفي"
            value={form.title}
            onChange={set('title')}
            required
            hint="يظهر في التصفّح — لا يُحجب."
          />
        ) : (
          <>
            <Field label="الاسم القانوني للشركة" value={form.legal_name} onChange={set('legal_name')} required />
            <Field label="رقم السجلّ التجاري" value={form.cr_number} onChange={set('cr_number')} />
          </>
        )}

        <Field label="البريد الإلكتروني" type="email" value={form.email} onChange={set('email')} required />
        <Field
          label="رقم الهاتف"
          value={form.phone}
          onChange={set('phone')}
          hint={isEmployee ? 'محجوب حتى يدفع صاحب العمل رسم الكشف.' : undefined}
        />
        <Field label="كلمة المرور" type="password" value={form.password} onChange={set('password')} required />
        <Field
          label="تأكيد كلمة المرور"
          type="password"
          value={form.password_confirmation}
          onChange={set('password_confirmation')}
          required
        />

        <div className="flex flex-col gap-2.5 rounded-xl bg-surface-muted p-3.5">
          <Checkbox checked={acceptTerms} onChange={setAcceptTerms}>
            أوافق على <Link to="/terms">شروط الاستخدام</Link>.
          </Checkbox>
          <Checkbox checked={acceptPrivacy} onChange={setAcceptPrivacy}>
            أوافق على <Link to="/privacy">سياسة الخصوصية</Link> ومعالجة بياناتي وفق قانون حماية البيانات القطري رقم 13.
          </Checkbox>
        </div>

        <SubmitButton pending={pending}>إنشاء الحساب</SubmitButton>

        <p className="text-center text-xs text-[var(--k-text-secondary)]">
          لديك حساب؟ <Link to="/login" className="font-medium">سجّل دخولك</Link>
        </p>
      </form>
    </AuthShell>
  )
}
