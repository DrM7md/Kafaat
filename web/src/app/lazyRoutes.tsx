import { lazy, Suspense, type ComponentType, type ReactElement } from 'react'
import { PageSkeleton } from '@/shared/ui/Skeleton'

/**
 * الصفحات النادرة الزيارة تُقتطع من الحزمة الأولى — لا يدفع ثمنَ لوحة
 * المشرف من لا يفتحها أبداً.
 *
 * ★ الاقتطاع وحده يجعل التنقّل **أبطأ**: أوّل نقرةٍ تنتظر تنزيل الجزء.
 *   فنُنزّله عند تمرير المؤشّر على رابط التنقّل. المسافة بين التمرير
 *   والنقر أطول من زمن تنزيل جزءٍ محلّي، فتصل النقرة والجزء جاهز.
 *
 * شاشة التصفّح والتفاصيل **ليست** هنا: هي المسار الساخن، وتبقى في الحزمة.
 */
/** الصفحات تُصدَّر بأسمائها، و`lazy` يطلب `default` — نُطابق عند النداء. */
type Loader = () => Promise<{ default: ComponentType }>

function lazyPage(loader: Loader) {
  const Component = lazy(loader)

  const element = (
    <Suspense fallback={<PageSkeleton />}>
      <Component />
    </Suspense>
  )

  return { element, prefetch: () => void loader() }
}

const companies = lazyPage(() =>
  import('@/features/admin/CompaniesPage').then((m) => ({ default: m.CompaniesPage })),
)
const companyDetail = lazyPage(() =>
  import('@/features/admin/CompanyDetailPage').then((m) => ({ default: m.CompanyDetailPage })),
)
const approvals = lazyPage(() =>
  import('@/features/admin/CandidateApprovalsPage').then((m) => ({ default: m.CandidateApprovalsPage })),
)
const roles = lazyPage(() =>
  import('@/features/admin/RoleMatrixPage').then((m) => ({ default: m.RoleMatrixPage })),
)
const settings = lazyPage(() =>
  import('@/features/admin/SettingsPage').then((m) => ({ default: m.SettingsPage })),
)
const audit = lazyPage(() =>
  import('@/features/admin/AuditLogPage').then((m) => ({ default: m.AuditLogPage })),
)

export const lazyPages = {
  companies: companies.element,
  companyDetail: companyDetail.element,
  approvals: approvals.element,
  roles: roles.element,
  settings: settings.element,
  audit: audit.element,
} satisfies Record<string, ReactElement>

/** يربط مسار التنقّل بجالب جزئه — يستعمله `AppLayout` عند تمرير المؤشّر. */
export const prefetchRouteChunk: Record<string, () => void> = {
  // تفاصيل الشركة تُفتح من داخل قائمة الشركات، فيُسخَّن جزآهما معاً.
  '/admin/companies': () => {
    companies.prefetch()
    companyDetail.prefetch()
  },
  '/admin/candidates': approvals.prefetch,
  '/admin/roles': roles.prefetch,
  '/admin/settings': settings.prefetch,
  '/admin/audit': audit.prefetch,
}
