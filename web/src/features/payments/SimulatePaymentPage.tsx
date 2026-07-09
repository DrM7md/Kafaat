import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'

/**
 * صفحة محاكاة الدفع — بيئة التطوير فقط.
 *
 * تحاكي «الصفحة المستضافة» لدى البوّابة. لا تُرسل webhook بنفسها: ذلك
 * يحتاج توقيع HMAC بسرٍّ لا يجوز أن يعرفه المتصفّح إطلاقاً.
 *
 * لتأكيد الدفع محلّياً، شغّل من الطرفية:
 *   php artisan kafaat:simulate-payment {provider_ref}
 *
 * حين تُعتمد بوّابة حقيقية (QPay/Dibsy)، تختفي هذه الصفحة ويحلّ محلّها
 * إعادة توجيهٍ إلى البوّابة. لا يتغيّر شيء في الخلفية.
 */
export function SimulatePaymentPage() {
  const [params] = useSearchParams()
  const [copied, setCopied] = useState(false)

  const ref = params.get('ref') ?? ''
  const command = `php artisan kafaat:simulate-payment ${ref}`

  return (
    <main className="grid min-h-screen place-items-center p-6">
      <div className="w-full max-w-lg rounded-[24px] border border-[var(--k-border)] bg-surface p-8 shadow-[var(--k-shadow-hero)]">
        <div
          className="mb-5 rounded-xl border p-3 text-center text-xs font-medium"
          style={{ borderColor: 'rgb(180 134 59 / 0.4)', background: 'var(--k-gold-tint)', color: '#96682A' }}
        >
          بوّابة تطوير — لا مال حقيقي
        </div>

        <h1 className="font-heading text-xl text-ink">إتمام الدفع</h1>
        <p className="mt-2 text-sm text-[var(--k-text-secondary)]">
          الحجب <strong>لم يُفكّ بعد</strong>. يُفكّ فقط حين يصل الخادمَ webhook موقّعٌ من البوّابة.
        </p>

        <div className="mt-5 rounded-xl bg-surface-muted p-4">
          <p className="text-[11px] text-[var(--k-text-muted)]">مرجع الدفعة</p>
          <p dir="ltr" className="mt-1 break-all font-mono text-[13px] text-ink">
            {ref === '' ? '—' : ref}
          </p>
        </div>

        <p className="mt-5 text-[13px] text-[var(--k-text-secondary)]">
          شغّل هذا الأمر في طرفية <code>api/</code> لمحاكاة تأكيد البوّابة:
        </p>

        <div className="mt-2 flex items-center gap-2 rounded-xl border border-[var(--k-border-strong)] bg-ink p-3">
          <code dir="ltr" className="flex-1 overflow-x-auto whitespace-nowrap text-[12px] text-[#E4EDEB]">
            {command}
          </code>
          <button
            type="button"
            onClick={() => {
              void navigator.clipboard.writeText(command)
              setCopied(true)
              setTimeout(() => setCopied(false), 1600)
            }}
            className="cursor-pointer rounded-lg bg-primary px-2.5 py-1.5 text-[11px] font-medium text-white"
          >
            {copied ? 'نُسخ' : 'نسخ'}
          </button>
        </div>

        <Link
          to="/candidates"
          className="mt-6 block rounded-[14px] bg-primary py-3 text-center text-sm font-bold text-white hover:brightness-110"
        >
          العودة إلى التصفّح
        </Link>
      </div>
    </main>
  )
}
