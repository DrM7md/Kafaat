import { createBrowserRouter } from 'react-router'
import { AppLayout } from '@/shared/ui/AppLayout'
import { GuestRoute, OwnAccountRoute, ProtectedRoute } from '@/shared/auth/Can'
import { LoginPage } from '@/features/auth/LoginPage'
import { RegisterPage } from '@/features/auth/RegisterPage'
import { ChooseAccountTypePage } from '@/features/auth/ChooseAccountTypePage'
import { NotFoundPage } from '@/shared/ui/NotFoundPage'
import { HomePage } from '@/features/home/HomePage'
import { BrowsePage } from '@/features/candidates/BrowsePage'
import { CandidateDetailPage } from '@/features/candidates/CandidateDetailPage'
import { MyRevealsPage } from '@/features/reveals/MyRevealsPage'
import { MyProfilePage } from '@/features/profile/MyProfilePage'
import { NotificationsPage } from '@/features/notifications/NotificationsPage'
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

      // النوع يُقرَّر هنا، ثمّ يُثبَّت في المسار — فالنموذجان لا يتقاسمان حالة.
      { path: '/register', element: <ChooseAccountTypePage /> },
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

          // بلا حارس صلاحية: لكل مستخدمٍ صندوقه، والخادم لا يُخرج من صفوفه.
          { path: '/notifications', element: <NotificationsPage /> },

          // ولا هنا: يعدّل حسابه هو. حقول الشركة تظهر لمن له ملفّ شركة وحده،
          // والخادم لا يكتبها لغيره.
          { path: '/account', element: lazyPages.account },

          {
            element: <ProtectedRoute permission="candidate.browse" />,
            children: [
              { path: '/candidates', element: <BrowsePage /> },
              { path: '/candidates/:ref', element: <CandidateDetailPage /> },
            ],
          },

          // ★ خدمةٌ ذاتية: `OwnAccountRoute` تشترط الصلاحية **وملفّاً** يملكها.
          //   إخفاءُ الرابط وحده لا يكفي: من يكتب `/profile` في شريط العنوان
          //   كان يعبر `ProtectedRoute` (المشرف العام يملك كل صلاحية) ثمّ
          //   يصطدم بـ 403 من الخادم. الآن يُردّ إلى الرئيسية.
          {
            element: <OwnAccountRoute kind="company" permission="candidate.reveal" />,
            children: [{ path: '/reveals', element: <MyRevealsPage /> }],
          },

          // ── الوظائف ──
          // خدمةٌ ذاتية لكل طرف: الشركة تنشر، والموظّف يتصفّح ويُبدي اهتمامه.
          // `OwnAccountRoute` تشترط الملفَّ **والصلاحية** — فالمشرف (الذي يملك
          // كل صلاحية عبر `Gate::before`) لا يرى شاشةً لا ملفَّ له فيها.
          {
            element: <OwnAccountRoute kind="company" permission="job.manage" />,
            children: [
              { path: '/my-jobs', element: lazyPages.myJobs },
              { path: '/jobs/:ref/interested', element: lazyPages.jobInterested },
            ],
          },

          {
            element: <OwnAccountRoute kind="employee" permission="job.browse" />,
            children: [{ path: '/jobs', element: lazyPages.jobBoard }],
          },

          {
            element: <OwnAccountRoute kind="employee" permission="profile.update" />,
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

          // ★ لوحة الإعدادات: بابُ أربع صفحاتٍ لكلٍّ صلاحيتها. من يملك واحدةً
          //   منها يستحقّ الباب — واللوحة نفسها لا تعرض إلّا بطاقاتِ ما يملك.
          //   اشتراطُ `settings.view` وحدها كان يحجب سجلَّ التدقيق عن مدقّقٍ
          //   لا يملك الإعدادات، بعد أن غادر رابطُه شريطَ التنقّل.
          {
            element: <ProtectedRoute permission={['settings.view', 'lookup.manage', 'audit.view']} />,
            children: [{ path: '/admin/settings', element: lazyPages.settingsHub }],
          },

          // المظهر تفضيلٌ شخصيّ (خطوط الواجهة) لا إعدادُ نظام — بلا حارس
          // صلاحية: يملكه كل مستخدمٍ لنفسه في متصفّحه.
          { path: '/admin/settings/appearance', element: lazyPages.appearance },

          {
            element: <ProtectedRoute permission="settings.view" />,
            children: [{ path: '/admin/settings/system', element: lazyPages.systemSettings }],
          },

          {
            element: <ProtectedRoute permission="lookup.manage" />,
            children: [{ path: '/admin/lookups', element: lazyPages.lookups }],
          },

          {
            element: <ProtectedRoute permission="settings.view" />,
            children: [{ path: '/admin/pricing', element: lazyPages.pricing }],
          },

          {
            element: <ProtectedRoute permission="audit.view" />,
            children: [{ path: '/admin/audit', element: lazyPages.audit }],
          },
        ],
      },
    ],
  },

  // آخر المطاف: أيّ رابطٍ لم يطابق ما سبق. خارج الحرّاس عمداً — صفحة
  // «غير موجودة» لا تُخفى عن زائرٍ لأنه غير مسجَّل.
  { path: '*', element: <NotFoundPage /> },
])
