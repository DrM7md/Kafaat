import { Link } from 'react-router'

/** أيّ رابطٍ لا يطابق مساراً. بلا هذه الصفحة يعرض react-router شاشة خطأ خامّة. */
export function NotFoundPage() {
  return (
    <main className="grid min-h-screen place-items-center p-6">
      <div className="w-full max-w-md rounded-[24px] bg-surface p-8 text-center shadow-[var(--k-sh-3)]">
        <p className="numeric font-heading text-5xl font-bold text-primary">404</p>

        <h1 className="mt-3 font-heading text-xl text-ink">الصفحة غير موجودة</h1>
        <p className="mt-1.5 text-sm text-[var(--k-text-secondary)]">
          الرابط الذي فتحته لا يقابل صفحةً في كفاءات — ربّما تغيّر أو كُتب خطأً.
        </p>

        <Link
          to="/"
          className="mt-6 inline-block rounded-[14px] bg-primary px-5 py-3 text-sm font-bold text-white transition-[filter] hover:brightness-110 hover:text-white"
        >
          العودة إلى الرئيسية
        </Link>
      </div>
    </main>
  )
}
