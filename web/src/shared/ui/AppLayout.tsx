import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { useAuth } from '@/shared/auth/AuthProvider'
import { Can } from '@/shared/auth/Can'

export function AppLayout() {
  const { me, logout } = useAuth()
  const navigate = useNavigate()

  async function handleLogout() {
    await logout()
    void navigate('/login')
  }

  return (
    <div className="min-h-screen">
      <header className="sticky top-0 z-10 border-b border-[var(--k-border)] bg-surface/85 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center gap-6 px-4 py-3">
          <Link to="/" className="flex items-baseline gap-2">
            <span className="font-heading text-2xl font-bold text-primary">كفاءات</span>
            <span className="text-[10px] tracking-[1px] text-[var(--k-text-muted)]">KAFAAT</span>
          </Link>

          <nav className="flex flex-1 items-center gap-1 text-sm">
            <Can permission="candidate.browse">
              <NavItem to="/candidates">تصفّح الكفاءات</NavItem>
            </Can>
            <Can permission="candidate.reveal">
              <NavItem to="/reveals">كشوفاتي</NavItem>
            </Can>
            <Can permission="profile.update">
              <NavItem to="/profile">ملفّي المهني</NavItem>
            </Can>
            <Can permission="profile.approve">
              <NavItem to="/admin/candidates">طلبات الكفاءات</NavItem>
            </Can>
            <Can permission="company.view">
              <NavItem to="/admin/companies">الشركات</NavItem>
            </Can>
            <Can permission="rbac.manage">
              <NavItem to="/admin/roles">الأدوار</NavItem>
            </Can>
            <Can permission="settings.view">
              <NavItem to="/admin/settings">الإعدادات</NavItem>
            </Can>
            <Can permission="audit.view">
              <NavItem to="/admin/audit">التدقيق</NavItem>
            </Can>
          </nav>

          {me !== null && (
            <div className="flex items-center gap-3">
              <span className="text-xs text-[var(--k-text-secondary)]">{me.user.name}</span>
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
  return (
    <NavLink
      to={to}
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
