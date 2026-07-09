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
import { RoleMatrixPage } from '@/features/admin/RoleMatrixPage'
import { SettingsPage } from '@/features/admin/SettingsPage'
import { AuditLogPage } from '@/features/admin/AuditLogPage'
import { SimulatePaymentPage } from '@/features/payments/SimulatePaymentPage'
import { HealthPage } from '@/features/health/HealthPage'

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

  // صفحة محاكاة الدفع تُفتح من إعادة توجيه البوّابة — بلا حارس.
  { path: '/payments/simulate', element: <SimulatePaymentPage /> },
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

          {
            element: <ProtectedRoute permission="rbac.manage" />,
            children: [{ path: '/admin/roles', element: <RoleMatrixPage /> }],
          },

          {
            element: <ProtectedRoute permission="settings.view" />,
            children: [{ path: '/admin/settings', element: <SettingsPage /> }],
          },

          {
            element: <ProtectedRoute permission="audit.view" />,
            children: [{ path: '/admin/audit', element: <AuditLogPage /> }],
          },
        ],
      },
    ],
  },
])
