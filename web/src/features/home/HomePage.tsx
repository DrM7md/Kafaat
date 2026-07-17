import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { useAuth } from '@/shared/auth/AuthProvider'
import { Can, useAccountKind } from '@/shared/auth/Can'

/**
 * لوحة الانطلاق — أوّل ما يراه الداخل.
 *
 * البطاقات مسوّرةٌ بالصلاحية (`<Can>`) فتختلف باختلاف الدور: المشرف يرى
 * الإدارة، والشركة ترى كشوفاتها، والموظّف ملفّه. الترتيب في المصدر هو ترتيب
 * الظهور والدخول المتتابع.
 */
export function HomePage() {
  const { me } = useAuth()
  const account = useAccountKind()

  return (
    <div className="mx-auto max-w-4xl">
      <header className="text-center" style={{ animation: 'kf-rise .6s var(--k-ease) both' }}>
        <span className="inline-flex items-center gap-2 rounded-full bg-mint px-3.5 py-1.5 text-[12px] font-semibold text-primary">
          <span className="relative flex size-2">
            <span
              className="absolute inline-flex size-full rounded-full bg-primary-lite opacity-60"
              style={{ animation: 'kf-ping 2.4s var(--k-ease) infinite' }}
            />
            <span className="relative inline-flex size-2 rounded-full bg-primary-lite" />
          </span>
          لوحة الانطلاق
        </span>

        <h1
          className="mt-4 font-heading text-[clamp(1.8rem,4vw,2.6rem)] font-bold tracking-tight text-ink"
          style={{ textWrap: 'balance' }}
        >
          أهلاً،{' '}
          <span className="relative isolate whitespace-nowrap text-primary">
            {me?.user.name}
            <span
              aria-hidden
              className="absolute inset-x-0 bottom-1 -z-10 h-2.5 rounded-full"
              style={{ background: 'linear-gradient(90deg, var(--k-mint), rgb(228 237 235 / 0.4))' }}
            />
          </span>
        </h1>

        <p className="mx-auto mt-2 max-w-[46ch] text-[15px] text-[var(--k-text-secondary)]">
          كفاءات — حيث تجد الشركةُ موظّفَها المناسب. اختر وجهتك.
        </p>
      </header>

      <div className="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Can permission="candidate.browse">
          <HubCard
            index={0}
            to="/candidates"
            icon={<UsersIcon />}
            title="تصفّح الكفاءات"
            body="ملفّات مهنية كاملة، بهويّات محجوبة حتى تدفع رسم الكشف."
          />
        </Can>

        {/* خدمةٌ ذاتية: تلزمها صلاحيةٌ **وملفٌّ** يملكها. راجع `useAccountKind`. */}
        {account === 'company' && (
          <Can permission="candidate.reveal">
            <HubCard
              index={1}
              to="/reveals"
              icon={<UnlockIcon />}
              title="كشوفاتي"
              body="سجلّ عمليات الكشف والمبالغ المجمّدة وقت تنفيذها."
            />
          </Can>
        )}

        {account === 'employee' && (
          <Can permission="profile.update">
            <HubCard
              index={1}
              to="/profile"
              icon={<IdIcon />}
              title="ملفّي المهني"
              body="اسمك وبيانات تواصلك محجوبة عن الشركات حتى تدفع رسم الكشف."
            />
          </Can>
        )}

        <Can permission="profile.approve">
          <HubCard
            index={2}
            to="/admin/candidates"
            icon={<InboxIcon />}
            title="طلبات الكفاءات"
            body="راجع الملفّات المُقدَّمة واعتمدها. لا يظهر ملفٌّ لأي شركة قبل اعتماده."
          />
        </Can>

        <Can permission="company.view">
          <HubCard
            index={3}
            to="/admin/companies"
            icon={<BuildingIcon />}
            title="الشركات"
            body="توثيق الشركات، ومتابعة طلبات الكشف ومدفوعاتها."
          />
        </Can>

        <Can permission="rbac.manage">
          <HubCard
            index={4}
            to="/admin/roles"
            icon={<ShieldIcon />}
            title="الأدوار والصلاحيات"
            body="أنشئ أدواراً ووزّع الصلاحيات دون تعديل برمجي."
          />
        </Can>

        <Can permission="settings.view">
          <HubCard
            index={5}
            to="/admin/settings"
            icon={<SlidersIcon />}
            title="الإعدادات"
            body="نموذج الربح، حجب الحقول، الخصوصية، مفاتيح الميزات."
          />
        </Can>

        <Can permission="audit.view">
          <HubCard
            index={6}
            to="/admin/audit"
            icon={<ScrollIcon />}
            title="سجلّ التدقيق"
            body="سجلّ إلحاقيّ لكل فعلٍ حسّاس. لا يُعدَّل ولا يُحذَف."
          />
        </Can>
      </div>
    </div>
  )
}

/**
 * بطاقة وجهة — أيقونةٌ في مربّعٍ نعناعيّ يلبس البترولي عند التأشير، وسهمٌ
 * ينزلق نحو القراءة (يساراً في RTL). تدخل بتتابعٍ محسوبٍ من `index`.
 */
function HubCard({
  to,
  title,
  body,
  icon,
  index,
}: {
  to: string
  title: string
  body: string
  icon: ReactNode
  index: number
}) {
  return (
    <Link
      to={to}
      style={{ animation: `kf-rise .55s var(--k-ease) ${(0.05 + index * 0.06).toFixed(2)}s both` }}
      className="group relative flex flex-col gap-3.5 overflow-hidden rounded-[22px] bg-surface p-5 text-right shadow-[var(--k-sh-2)] transition-[transform,box-shadow] duration-300 hover:-translate-y-1.5 hover:shadow-[var(--k-sh-3)]"
    >
      <div className="flex items-center justify-between">
        <span className="grid size-11 place-items-center rounded-[14px] bg-mint text-primary transition-colors duration-300 group-hover:bg-primary group-hover:text-white">
          {icon}
        </span>
        <ArrowIcon className="text-[var(--k-text-muted)] transition-[transform,color] duration-300 group-hover:-translate-x-1 group-hover:text-primary" />
      </div>

      <div>
        <p className="font-heading text-[17px] font-bold text-ink">{title}</p>
        <p className="mt-1.5 text-[12.5px] leading-6 text-[var(--k-text-secondary)]">{body}</p>
      </div>
    </Link>
  )
}

/* ── الأيقونات ── */

function ArrowIcon({ className }: { className?: string }) {
  return (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className={className} aria-hidden>
      <path d="M19 12H5M12 19l-7-7 7-7" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}

function UsersIcon() {
  return (
    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <circle cx="9" cy="8" r="3.2" />
      <path d="M3.5 20a5.5 5.5 0 0 1 11 0" strokeLinecap="round" />
      <path d="M16 5.2a3.2 3.2 0 0 1 0 5.6M17.5 20a5.5 5.5 0 0 0-3-4.9" strokeLinecap="round" />
    </svg>
  )
}

function UnlockIcon() {
  return (
    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <rect x="4" y="10.5" width="16" height="10" rx="2.5" />
      <path d="M8 10.5V7a4 4 0 0 1 7.5-1.9" strokeLinecap="round" />
      <circle cx="12" cy="15.5" r="1.4" fill="currentColor" stroke="none" />
    </svg>
  )
}

function IdIcon() {
  return (
    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <rect x="3" y="5" width="18" height="14" rx="2.5" />
      <circle cx="8.5" cy="11" r="2" />
      <path d="M5.5 16a3 3 0 0 1 6 0M14 10h4M14 13.5h3" strokeLinecap="round" />
    </svg>
  )
}

function InboxIcon() {
  return (
    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M3 13l2.5-7.5A2 2 0 0 1 7.4 4h9.2a2 2 0 0 1 1.9 1.5L21 13v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" strokeLinejoin="round" />
      <path d="M3 13h5a2 2 0 0 0 4 0h9" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}

function BuildingIcon() {
  return (
    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M4 21V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v16" strokeLinejoin="round" />
      <path d="M15 9h3a2 2 0 0 1 2 2v10M2 21h20M7.5 7h3M7.5 11h3M7.5 15h3" strokeLinecap="round" />
    </svg>
  )
}

function ShieldIcon() {
  return (
    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 3l7 3v5c0 4.4-3 8.2-7 9-4-0.8-7-4.6-7-9V6Z" strokeLinejoin="round" />
      <path d="m9 12 2 2 4-4" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}

function SlidersIcon() {
  return (
    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M4 8h9M17 8h3M4 16h3M11 16h9" strokeLinecap="round" />
      <circle cx="15" cy="8" r="2.2" />
      <circle cx="9" cy="16" r="2.2" />
    </svg>
  )
}

function ScrollIcon() {
  return (
    <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M6 4h10a2 2 0 0 1 2 2v11a3 3 0 0 0 3 3H8a3 3 0 0 1-3-3V6" strokeLinejoin="round" />
      <path d="M9 8h6M9 11.5h6M9 15h4" strokeLinecap="round" />
    </svg>
  )
}
