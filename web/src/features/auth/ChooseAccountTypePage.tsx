import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { AuthShell } from './AuthShell'

/**
 * مفترق التسجيل. النوع يُقرَّر هنا مرّةً واحدة، لأنّه يحدّد كل ما بعده:
 * حقول النموذج، ومسار الـ API، والدور، وما إذا كان الملفّ يُحجب أم يتصفّح.
 *
 * العبارة تصف ما يريده الزائر، لا ما يسمّيه النظام: من يبحث عن عمل لا يعرّف
 * نفسه بأنه «موظّف» في منصّة، بل بأنه يبحث عن فرصة أفضل.
 */
export function ChooseAccountTypePage() {
  return (
    <AuthShell wide title="أنشئ حسابك" subtitle="اختر ما يصفك — لكلٍّ رحلته في كفاءات.">
      <div className="grid gap-4 sm:grid-cols-2">
        <ChoiceCard
          to="/register/employee"
          icon={<SeekerIcon />}
          title="أبحث عن فرصة أفضل"
          body="ملفّك المهني يظهر للشركات، لكن اسمك وبيانات تواصلك محجوبة — لا تُكشف إلّا حين تدفع شركةٌ مهتمّة رسم الكشف."
          note="مجّاني للباحثين عن عمل"
        />

        <ChoiceCard
          to="/register/company"
          icon={<CompanyIcon />}
          title="أبحث عن موظّف"
          body="تصفّح كفاءات مؤهَّلة بملفّات كاملة، وادفع رسم الكشف عند من يناسبك وحده."
          note="الدفع عند الكشف فقط"
        />
      </div>

      <p className="mt-6 text-center text-xs text-[var(--k-text-secondary)]">
        لديك حساب؟{' '}
        <Link to="/login" className="font-medium">
          سجّل دخولك
        </Link>
      </p>
    </AuthShell>
  )
}

function ChoiceCard({
  to,
  icon,
  title,
  body,
  note,
}: {
  to: string
  icon: ReactNode
  title: string
  body: string
  note: string
}) {
  return (
    <Link
      to={to}
      className="group flex flex-col rounded-2xl border border-[var(--k-border-strong)] bg-surface p-5 transition-[transform,box-shadow] duration-[220ms] ease-out hover:-translate-y-1 hover:border-primary hover:shadow-[var(--k-shadow-hover)] focus-visible:-translate-y-1 focus-visible:shadow-[var(--k-shadow-hover)]"
    >
      <span className="grid size-11 place-items-center rounded-[14px] bg-mint text-primary">{icon}</span>

      <span className="mt-3.5 font-heading text-[17px] text-ink">{title}</span>
      <span className="mt-1.5 grow text-[12.5px] leading-relaxed text-[var(--k-text-secondary)]">{body}</span>

      <span className="mt-4 flex items-center justify-between border-t border-[var(--k-divider)] pt-3">
        <span className="text-[11px] text-[var(--k-text-muted)]">{note}</span>
        <span className="text-[13px] font-semibold text-primary">
          التالي <span className="inline-block transition-transform group-hover:-translate-x-0.5">←</span>
        </span>
      </span>
    </Link>
  )
}

function SeekerIcon() {
  return (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
      <circle cx="12" cy="8" r="4" />
      <path d="M5.5 21a6.5 6.5 0 0 1 13 0" strokeLinecap="round" />
    </svg>
  )
}

function CompanyIcon() {
  return (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
      <path d="M3 21h18" strokeLinecap="round" />
      <path d="M5 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16" />
      <path d="M15 21V11h4a2 2 0 0 1 2 2v8" />
      <path d="M9 7h2M9 11h2M9 15h2" strokeLinecap="round" />
    </svg>
  )
}
