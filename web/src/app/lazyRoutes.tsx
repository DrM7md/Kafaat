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

// «حسابي» ليست لوحة مشرف، لكنها كذلك نادرةُ الزيارة: تُفتح مرّةً عند التسجيل
// ثم لا تُفتح شهوراً. لا تستحقّ مكاناً في الحزمة الأولى.
const account = lazyPage(() =>
  import('@/features/account/AccountPage').then((m) => ({ default: m.AccountPage })),
)

// الوظائف: قسمٌ كامل لا يفتحه إلّا من يعنيه — شركةٌ تنشر، أو باحثٌ يتصفّح.
const jobBoard = lazyPage(() =>
  import('@/features/jobs/JobBoardPage').then((m) => ({ default: m.JobBoardPage })),
)
const myJobs = lazyPage(() =>
  import('@/features/jobs/MyJobsPage').then((m) => ({ default: m.MyJobsPage })),
)
const jobInterested = lazyPage(() =>
  import('@/features/jobs/JobInterestedPage').then((m) => ({ default: m.JobInterestedPage })),
)

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
const settingsHub = lazyPage(() =>
  import('@/features/admin/SettingsHubPage').then((m) => ({ default: m.SettingsHubPage })),
)
const systemSettings = lazyPage(() =>
  import('@/features/admin/SystemSettingsPage').then((m) => ({ default: m.SystemSettingsPage })),
)
const lookups = lazyPage(() =>
  import('@/features/admin/LookupsPage').then((m) => ({ default: m.LookupsPage })),
)
const pricing = lazyPage(() =>
  import('@/features/admin/RevealPricingPage').then((m) => ({ default: m.RevealPricingPage })),
)
const audit = lazyPage(() =>
  import('@/features/admin/AuditLogPage').then((m) => ({ default: m.AuditLogPage })),
)
const appearance = lazyPage(() =>
  import('@/features/admin/AppearancePage').then((m) => ({ default: m.AppearancePage })),
)

export const lazyPages = {
  account: account.element,
  jobBoard: jobBoard.element,
  myJobs: myJobs.element,
  jobInterested: jobInterested.element,
  companies: companies.element,
  companyDetail: companyDetail.element,
  approvals: approvals.element,
  roles: roles.element,
  settingsHub: settingsHub.element,
  systemSettings: systemSettings.element,
  lookups: lookups.element,
  pricing: pricing.element,
  audit: audit.element,
  appearance: appearance.element,
} satisfies Record<string, ReactElement>

/** يربط مسار التنقّل بجالب جزئه — يستعمله `AppLayout` عند تمرير المؤشّر. */
export const prefetchRouteChunk: Record<string, () => void> = {
  '/account': account.prefetch,
  '/jobs': jobBoard.prefetch,

  // قائمة المهتمّين تُفتح من داخل «وظائفي» — فيُسخَّن جزآهما معاً.
  '/my-jobs': () => {
    myJobs.prefetch()
    jobInterested.prefetch()
  },

  // تفاصيل الشركة تُفتح من داخل قائمة الشركات، فيُسخَّن جزآهما معاً.
  '/admin/companies': () => {
    companies.prefetch()
    companyDetail.prefetch()
  },
  '/admin/candidates': approvals.prefetch,
  '/admin/roles': roles.prefetch,

  // اللوحة بابُ الأربعة، ولا يُسخَّن معها إلّا جزؤها: تسخينُ الأربعة عند
  // تمرير المؤشّر على «الإعدادات» يُنزّل ما لن يُفتح غالباً. البطاقة نفسها
  // تُسخّن وجهتَها حين يمرّ المؤشّر عليها — وهناك تكون النيّة قد تحدّدت.
  '/admin/settings': settingsHub.prefetch,
  '/admin/settings/system': systemSettings.prefetch,
  '/admin/lookups': lookups.prefetch,
  '/admin/pricing': pricing.prefetch,
  '/admin/audit': audit.prefetch,
  '/admin/settings/appearance': appearance.prefetch,
}
