import { createBrowserRouter } from 'react-router'
import { AppLayout } from '@/shared/ui/AppLayout'
import { GuestRoute, ProtectedRoute } from '@/shared/auth/Can'
import { LoginPage } from '@/features/auth/LoginPage'
import { RegisterPage } from '@/features/auth/RegisterPage'
import { HomePage } from '@/features/home/HomePage'
import { BrowsePage } from '@/features/candidates/BrowsePage'
import { CandidateDetailPage } from '@/features/candidates/CandidateDetailPage'
import { MyRevealsPage } from '@/features/reveals/MyRevealsPage'
import { MyProfilePage } from '@/features/profile/MyProfilePage'
import { SimulatePaymentPage } from '@/features/payments/SimulatePaymentPage'
import { HealthPage } from '@/features/health/HealthPage'
import { lazyPages } from './lazyRoutes'

/**
 * ⚠️ `ProtectedRoute` يمنع الوصول إلى شاشة. **لا يحمي بياناً.**
 * كل مسار هنا يقابله حارسٌ في الخلفية (Policy + Middleware + Resource).
 */
export const router = createBrowserRouter([
  {
    element: <GuestRoute />,
    children: [
      { path: '/login', element: <LoginPage /> },
      { path: '/register/employee', element: <RegisterPage kind="employee" /> },
      { path: '/register/company', element: <RegisterPage kind="company" /> },
    ],
  },

  // صفحة محاكاة الدفع أداة تطويرٍ فقط — لا تُشحن في حزمة الإنتاج.
  {
    path: '/payments/simulate',
    element: import.meta.env.DEV ? (
      <SimulatePaymentPage />
    ) : (
      <main className="grid min-h-screen place-items-center p-6">
        <p className="text-sm text-[var(--k-text-muted)]">غير متاح</p>
      </main>
    ),
  },
  { path: '/health', element: <HealthPage /> },

  {
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { path: '/', element: <HomePage /> },

          {
            element: <ProtectedRoute permission="candidate.browse" />,
            children: [
              { path: '/candidates', element: <BrowsePage /> },
              { path: '/candidates/:ref', element: <CandidateDetailPage /> },
            ],
          },

          {
            element: <ProtectedRoute permission="candidate.reveal" />,
            children: [{ path: '/reveals', element: <MyRevealsPage /> }],
          },

          {
            element: <ProtectedRoute permission="profile.update" />,
            children: [{ path: '/profile', element: <MyProfilePage /> }],
          },

          // ── لوحة المشرف: أجزاءٌ مؤجّلة، تُسخَّن عند تمرير المؤشّر على رابطها ──
          {
            element: <ProtectedRoute permission="company.view" />,
            children: [
              { path: '/admin/companies', element: lazyPages.companies },
              { path: '/admin/companies/:id', element: lazyPages.companyDetail },
            ],
          },

          {
            element: <ProtectedRoute permission="profile.approve" />,
            children: [{ path: '/admin/candidates', element: lazyPages.approvals }],
          },

          {
            element: <ProtectedRoute permission="rbac.manage" />,
            children: [{ path: '/admin/roles', element: lazyPages.roles }],
          },

          {
            element: <ProtectedRoute permission="settings.view" />,
            children: [{ path: '/admin/settings', element: lazyPages.settings }],
          },

          {
            element: <ProtectedRoute permission="audit.view" />,
            children: [{ path: '/admin/audit', element: lazyPages.audit }],
          },
        ],
      },
    ],
  },
])
