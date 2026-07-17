import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { useAuth } from '@/shared/auth/AuthProvider'
import { Can, useAccountKind } from '@/shared/auth/Can'
import { useWarmCache } from '@/shared/hooks/useWarmCache'
import { NotificationBell } from '@/features/notifications/NotificationBell'
import { SceneBackground } from '@/shared/ui/SceneBackground'
import { prefetchRouteChunk } from '@/app/lazyRoutes'

export function AppLayout() {
  const { me, logout } = useAuth()
  const navigate = useNavigate()

  // `null` للمشرف: لا ملفَّ موظّفٍ له ولا ملفَّ شركة.
  const account = useAccountKind()

  // القوائم المرجعية والصفحة الأولى تُجلب الآن، لا عند أوّل نقرة.
  useWarmCache()

  async function handleLogout() {
    await logout()
    void navigate('/login')
  }

  return (
    <div className="min-h-screen">
      {/* خلفية المشهد — شبكة تخطيطية تنجرف + حبيبات ورق، خلف كل شيء */}
      <SceneBackground />

      <header className="sticky top-0 z-10 border-b border-[var(--k-border)] bg-surface/85 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center gap-6 px-4 py-3">
          <Link to="/" className="flex items-baseline gap-2">
            <span className="font-logo text-2xl font-bold text-primary">كفاءات</span>
            <span className="text-[10px] tracking-[1px] text-[var(--k-text-muted)]">KAFAAT</span>
          </Link>

          <nav className="flex flex-1 items-center gap-1 text-sm">
            <Can permission="candidate.browse">
              <NavItem to="/candidates">تصفّح الكفاءات</NavItem>
            </Can>
            {/* ★ خدمةٌ ذاتية: الصلاحية **وحدها** لا تكفي. `Gate::before` يمنح
                المشرفَ العام كل صلاحية، فكان يرى «كشوفاتي» و«ملفّي المهني»
                ولا ملفَّ له — ونقرُهما يصطدم بـ 403. السؤال الصحيح: هل يملك
                حسابُه ملفّاً أصلاً؟ */}
            {account === 'company' && (
              <Can permission="job.manage">
                <NavItem to="/my-jobs">وظائفي</NavItem>
              </Can>
            )}
            {account === 'employee' && (
              <Can permission="job.browse">
                <NavItem to="/jobs">الوظائف</NavItem>
              </Can>
            )}
            {account === 'company' && (
              <Can permission="candidate.reveal">
                <NavItem to="/reveals">كشوفاتي</NavItem>
              </Can>
            )}
            {account === 'employee' && (
              <Can permission="profile.update">
                <NavItem to="/profile">ملفّي المهني</NavItem>
              </Can>
            )}
            <Can permission="profile.approve">
              <NavItem to="/admin/candidates">طلبات الكفاءات</NavItem>
            </Can>
            <Can permission="company.view">
              <NavItem to="/admin/companies">الشركات</NavItem>
            </Can>
            <Can permission="rbac.manage">
              <NavItem to="/admin/roles">الأدوار</NavItem>
            </Can>

            {/* ★ القوائم والأسعار والتدقيق ليست هنا عمداً: صارت بطاقاتٍ داخل
                «الإعدادات». الشريط للمسار اليومي، وهي تُفتح مرّةً في الشهر —
                فكانت تُزاحم ما يُفتح كل يوم. */}
            <Can permission={['settings.view', 'lookup.manage', 'audit.view']}>
              <NavItem to="/admin/settings">الإعدادات</NavItem>
            </Can>
          </nav>

          {me !== null && (
            <div className="flex items-center gap-3">
              <NotificationBell />

              {/* الاسم هو الباب إلى الحساب — حيث يبحث عنه الناس أوّلاً، ولا
                  يستحقّ بنداً في التنقّل يُزاحم ما يُفتح كل يوم. */}
              <NavLink
                to="/account"
                onMouseEnter={() => prefetchRouteChunk['/account']?.()}
                onFocus={() => prefetchRouteChunk['/account']?.()}
                className={({ isActive }) =>
                  `rounded-lg px-2 py-1 text-xs font-medium transition-colors ${
                    isActive
                      ? 'bg-mint text-primary'
                      : 'text-[var(--k-text-secondary)] hover:bg-surface-muted hover:text-ink'
                  }`
                }
              >
                {me.user.name}
              </NavLink>

              <button
                type="button"
                onClick={() => void handleLogout()}
                className="cursor-pointer rounded-lg border border-[var(--k-border)] px-3 py-1.5 text-xs font-medium hover:bg-surface-muted"
              >
                خروج
              </button>
            </div>
          )}
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-4 py-8">
        <Outlet />
      </main>

      <footer className="border-t border-[var(--k-border)] py-6 text-center text-xs text-[var(--k-text-muted)]">
        كفاءات — حيث تجد الشركةُ موظّفَها المناسب
      </footer>
    </div>
  )
}

function NavItem({ to, children }: { to: string; children: string }) {
  // تمرير المؤشّر نيّةُ نقرٍ: نُنزّل جزء الصفحة قبل أن تصل النقرة.
  const prefetch = () => prefetchRouteChunk[to]?.()

  return (
    <NavLink
      to={to}
      onMouseEnter={prefetch}
      onFocus={prefetch}
      className={({ isActive }) =>
        `rounded-lg px-3 py-1.5 font-medium transition-colors ${
          isActive ? 'bg-mint text-primary' : 'text-[var(--k-text-secondary)] hover:bg-surface-muted'
        }`
      }
    >
      {children}
    </NavLink>
  )
}
