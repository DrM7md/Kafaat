import type { ReactNode } from 'react'
import { Navigate, Outlet } from 'react-router'
import { useAuth } from './AuthProvider'
import { isCompanyAccount, type Permission } from '@/shared/api/types'
import { PendingVerificationPage } from '@/features/company/PendingVerificationPage'

/**
 * ⚠️ `<Can>` يخفي عنصراً في الواجهة. **لا يحمي شيئاً.**
 *
 * البيانات التي لا يجوز للمستخدم رؤيتها لا تصل متصفّحه أصلاً — الخادم
 * لا يرسلها. وأي فعلٍ يمنعه هذا المكوّن، تمنعه الـ Policy في الخلفية أيضاً.
 *
 * Brain/01-architecture.md §2.
 */
/**
 * قائمةٌ من الصلاحيات تعني **أيّاً منها** لا كلَّها.
 *
 * تلزم للأبواب الجامعة: لوحةُ الإعدادات تفتح على أربع صفحات لكلٍّ صلاحيتها،
 * فمن يملك واحدةً منها يستحقّ الباب — واشتراطُ الكلّ كان يحجب اللوحة عمّن
 * يملك صفحةً فيها.
 */
type Required = Permission | Permission[]

function allows(can: (permission: Permission) => boolean, required: Required): boolean {
  return Array.isArray(required) ? required.some(can) : can(required)
}

export function Can({
  permission,
  children,
  fallback = null,
}: {
  permission: Required
  children: ReactNode
  fallback?: ReactNode
}) {
  const { can } = useAuth()

  return allows(can, permission) ? <>{children}</> : <>{fallback}</>
}

export function ProtectedRoute({ permission }: { permission?: Required }) {
  const { me, isLoading, can } = useAuth()
  const awaitingVerification = useAwaitingVerification()

  if (isLoading) {
    return <FullPageSpinner />
  }

  if (me === null) {
    return <Navigate to="/login" replace />
  }

  // ★ شركةٌ لم تُوثَّق: لا تصل شاشةً واحدة من الموقع. تحلّ محلَّها شاشةُ حالتها،
  //   لا رسالةُ خطأ خامّة عند أوّل نقرة.
  if (awaitingVerification) {
    return <PendingVerificationPage />
  }

  if (permission !== undefined && !allows(can, permission)) {
    return <Navigate to="/" replace />
  }

  return <Outlet />
}

/**
 * أيّ ملفٍّ يملكه هذا الحساب — إن ملك.
 *
 * ★★ سؤالٌ **بنيويّ**، لا سؤال صلاحية. والخلط بينهما هو العطب نفسه:
 *
 *    «ملفّي المهني» و«كشوفاتي» شاشتا خدمةٍ ذاتية — لا معنى لهما بلا ملفٍّ
 *    يملكهما صاحبُهما. وحراستُهما بـ `<Can permission="profile.update">`
 *    خاطئة، لأن `Gate::before` يمنح المشرفَ العام **كل** صلاحية: فيرى روابطَ
 *    ملفٍّ لا وجود له، ويصطدم بـ 403 حين ينقر. (والخادم يفشل مغلقاً بحقّ:
 *    `ownProfile()` تردّ «هذا الحساب ليس حساب موظّف».)
 *
 *    فنسأل ما يجب: **هل لهذا الحساب ملفّ؟** و`/me` تُجيب — `profile` تعود
 *    `null` للمشرف.
 *
 * ⚠️ لا يخالف هذا ADR-007: `type` لا يُتّخذ به قرارُ صلاحية، بل يُميّز شكل
 *    الحساب. مَن يجوز له الفعل يبقى قرارَ الـ Gate وحدها.
 */
export function useAccountKind(): 'employee' | 'company' | null {
  const { me } = useAuth()

  if (me === null || me.user.profile === null) {
    return null
  }

  const type = me.user.type

  return type === 'employee' || type === 'company' ? type : null
}

/**
 * هل هذه شركةٌ لم تُوثَّق بعد؟
 *
 * ★★ بوّابة الشركات: من يتصفّح هو من يرى ملفّات الناس، فلا تُفتح لشركةٍ لم
 *    يتحقّق منها إنسان. الخادم يردّ التصفّح والكشف بـ 403 (EnsureCompanyVerified)
 *    — وهذا الخطّاف يُبدّل الشاشةَ فلا تصطدم الشركةُ برسالة خطأ خامّة.
 *
 * ⚠️ تحسينُ تجربةٍ لا حاجز. لو زوّره أحدٌ في المتصفّح لما رأى مرشّحاً واحداً:
 *    البيانات لا تغادر الخادم أصلاً.
 */
export function useAwaitingVerification(): boolean {
  const { me } = useAuth()

  if (me === null) {
    return false
  }

  const profile = me.user.profile

  if (!isCompanyAccount(profile, me.user.type)) {
    return false
  }

  return profile.verification_status !== 'verified'
}

/**
 * حارس شاشات الخدمة الذاتية: صلاحيةٌ **و**ملفٌّ يملكها.
 *
 * الشرطان معاً لا أحدهما: الصلاحيةُ وحدها تُدخل المشرفَ العام إلى شاشةٍ
 * تردّه بـ 403، والملفُّ وحده يُدخل موظّفاً نُزعت عنه صلاحيةُ التحرير.
 */
export function OwnAccountRoute({
  kind,
  permission,
}: {
  kind: 'employee' | 'company'
  permission: Required
}) {
  const { me, isLoading, can } = useAuth()
  const account = useAccountKind()

  if (isLoading) {
    return <FullPageSpinner />
  }

  if (me === null) {
    return <Navigate to="/login" replace />
  }

  return account === kind && allows(can, permission) ? <Outlet /> : <Navigate to="/" replace />
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
