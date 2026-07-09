import type { ReactNode } from 'react'
import { Navigate, Outlet } from 'react-router'
import { useAuth } from './AuthProvider'
import type { Permission } from '@/shared/api/types'

/**
 * ⚠️ `<Can>` يخفي عنصراً في الواجهة. **لا يحمي شيئاً.**
 *
 * البيانات التي لا يجوز للمستخدم رؤيتها لا تصل متصفّحه أصلاً — الخادم
 * لا يرسلها. وأي فعلٍ يمنعه هذا المكوّن، تمنعه الـ Policy في الخلفية أيضاً.
 *
 * Brain/01-architecture.md §2.
 */
export function Can({
  permission,
  children,
  fallback = null,
}: {
  permission: Permission
  children: ReactNode
  fallback?: ReactNode
}) {
  const { can } = useAuth()

  return can(permission) ? <>{children}</> : <>{fallback}</>
}

export function ProtectedRoute({ permission }: { permission?: Permission }) {
  const { me, isLoading, can } = useAuth()

  if (isLoading) {
    return <FullPageSpinner />
  }

  if (me === null) {
    return <Navigate to="/login" replace />
  }

  if (permission !== undefined && !can(permission)) {
    return <Navigate to="/" replace />
  }

  return <Outlet />
}

export function GuestRoute() {
  const { me, isLoading } = useAuth()

  if (isLoading) {
    return <FullPageSpinner />
  }

  return me === null ? <Outlet /> : <Navigate to="/" replace />
}

function FullPageSpinner() {
  return (
    <div className="grid min-h-screen place-items-center">
      <div
        role="status"
        aria-label="جارٍ التحميل"
        className="size-8 animate-spin rounded-full border-2 border-[var(--k-border)] border-t-primary"
      />
    </div>
  )
}
