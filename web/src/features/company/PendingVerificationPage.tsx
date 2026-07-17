import { useAuth } from '@/shared/auth/AuthProvider'
import { isCompanyAccount } from '@/shared/api/types'

/**
 * شاشة الشركة قبل التوثيق — تحلّ محلّ الموقع كلّه.
 *
 * ★ لماذا نُبقي الدخول ممكناً بدل منعه؟
 *
 *   لأن منع الدخول يترك الشركةَ أمام بابٍ مغلق لا تدري ما وراءه: أنسيَها
 *   أحد؟ أرُفض طلبُها؟ أعُطّل الموقع؟ فتتصل بالدعم — أو تنصرف. الدخولُ إلى
 *   شاشةٍ تقول «طلبك قيد المراجعة» يُجيبها قبل أن تسأل.
 *
 * ⚠️ وهذه الشاشة **لا تحمي شيئاً**: الخادم يردّ التصفّح والكشف بـ 403 حتى
 *    لو زوّرها أحدٌ في المتصفّح (EnsureCompanyVerified).
 */
export function PendingVerificationPage() {
  const { me, logout } = useAuth()

  const profile = me?.user.profile ?? null
  const company = me !== undefined && me !== null && isCompanyAccount(profile, me.user.type) ? profile : null

  const rejected = company?.verification_status === 'rejected'

  return (
    <main className="grid min-h-screen place-items-center p-6">
      <div className="w-full max-w-lg rounded-[24px] bg-surface p-8 text-center shadow-[var(--k-sh-3)]">
        <div className="mb-6 flex flex-col items-center gap-1">
          <span className="font-logo text-3xl font-bold text-primary">كفاءات</span>
          <span className="text-[10px] tracking-[1px] text-[var(--k-text-muted)]">KAFAAT</span>
        </div>

        <span
          className="mx-auto grid size-14 place-items-center rounded-[18px] text-2xl"
          style={
            rejected
              ? { background: 'rgb(200 60 60 / 0.1)', color: '#8a2b2b' }
              : { background: 'var(--k-gold-tint)', color: '#96682A' }
          }
          aria-hidden
        >
          {rejected ? '!' : '⏳'}
        </span>

        <h1 className="mt-4 font-heading text-xl text-ink">
          {rejected ? 'لم يُقبل توثيق شركتك' : 'حسابك قيد المراجعة'}
        </h1>

        <p className="mt-2 text-sm leading-7 text-[var(--k-text-secondary)]">
          {rejected ? (
            <>
              تعذّر توثيق «{company?.legal_name}». تواصل مع الدعم لمعرفة السبب واستكمال ما ينقص.
            </>
          ) : (
            <>
              نتحقّق من بيانات «{company?.legal_name}» قبل فتح الكفاءات لك. هذا ما يحمي ملفّات الناس من
              الحسابات الوهمية — ولن يطول.
            </>
          )}
        </p>

        {!rejected && (
          <p className="mt-4 rounded-xl bg-surface-muted p-3.5 text-[12.5px] leading-6 text-[var(--k-text-secondary)]">
            سيصلك إشعارٌ فور توثيق حسابك، وتُفتح لك الكفاءات في اللحظة نفسها — بلا خطوةٍ منك.
          </p>
        )}

        <div className="mt-6 flex items-center justify-center gap-3">
          <a
            href="mailto:support@kafaat.qa"
            className="rounded-[14px] bg-primary px-5 py-3 text-sm font-bold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:text-white hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0"
          >
            تواصل مع الدعم
          </a>

          <button
            type="button"
            onClick={() => void logout()}
            className="cursor-pointer rounded-[14px] border border-[var(--k-border-strong)] px-5 py-3 text-sm font-medium text-[var(--k-text-secondary)] transition hover:-translate-y-0.5 hover:border-primary"
          >
            خروج
          </button>
        </div>
      </div>
    </main>
  )
}
