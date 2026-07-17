import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { api, apiErrorMessage } from '@/shared/api/client'
import { useAuth } from '@/shared/auth/AuthProvider'
import { AuthShell, Field, SubmitButton } from './AuthShell'

export function LoginPage() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)

  const { refresh } = useAuth()
  const navigate = useNavigate()

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    setPending(true)
    setError(null)

    try {
      // لا توكن يُعاد. الخادم يضع كوكي جلسة httpOnly (ADR-002).
      await api.post('/auth/login', { email, password })
      await refresh()
      void navigate('/')
    } catch (err) {
      setError(apiErrorMessage(err))
    } finally {
      setPending(false)
    }
  }

  return (
    <AuthShell title="تسجيل الدخول" subtitle="أهلاً بعودتك إلى كفاءات.">
      <form onSubmit={(e) => void handleSubmit(e)} className="flex flex-col gap-4">
        {error !== null && (
          <p className="rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
            {error}
          </p>
        )}

        <Field label="البريد الإلكتروني" type="email" value={email} onChange={setEmail} autoComplete="email" required />
        <Field
          label="كلمة المرور"
          type="password"
          value={password}
          onChange={setPassword}
          autoComplete="current-password"
          required
        />

        <SubmitButton pending={pending}>دخول</SubmitButton>

        <p className="text-center text-xs text-[var(--k-text-secondary)]">
          لا تملك حساباً؟{' '}
          <Link to="/register" className="font-medium">
            أنشئ حسابك
          </Link>
        </p>
      </form>
    </AuthShell>
  )
}
