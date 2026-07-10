import { createContext, use, useEffect, useMemo, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ensureCsrfCookie, registerUnauthorizedHandler, resetCsrf } from '@/shared/api/client'
import type { Me, Permission } from '@/shared/api/types'

type AuthValue = {
  me: Me | null
  isLoading: boolean
  can: (permission: Permission) => boolean
  refresh: () => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthValue | null>(null)

export function AuthProvider({ children }: Readonly<{ children: ReactNode }>) {
  const queryClient = useQueryClient()

  // 401 غير متوقّع (جلسة انتهت على الخادم) ⇒ امسح ذاكرة المصادقة، فيصير
  // `me` فارغاً ويحوّل <ProtectedRoute> إلى /login. مسارات المصادقة نفسها
  // مستثناةٌ في العميل كي لا تنشأ حلقة.
  useEffect(() => {
    registerUnauthorizedHandler(() => {
      queryClient.clear()
      queryClient.setQueryData(['me'], null)
    })
  }, [queryClient])

  const { data, isLoading } = useQuery({
    queryKey: ['me'],
    queryFn: async (): Promise<Me | null> => {
      try {
        await ensureCsrfCookie()
        return (await api.get<Me>('/me')).data
      } catch {
        return null // غير مصادَق — حالةٌ طبيعية لا خطأ.
      }
    },
    staleTime: 5 * 60_000,
    retry: false,
  })

  const me = data ?? null

  const value = useMemo<AuthValue>(
    () => ({
      me,
      isLoading,

      /**
       * ⚠️ للعرض فقط. `can()` يخفي زرّاً — لا يحمي شيئاً.
       * لو حذف المستخدم الزرّ من DOM ونادى الـ API، فالـ Policy هي من تردّه.
       * الواجهة تجميل، والخلفية قانون. Brain/04-rbac.md §5.
       */
      can: (permission) => me?.permissions.includes(permission) ?? false,

      refresh: async () => {
        await queryClient.invalidateQueries({ queryKey: ['me'] })
      },

      logout: async () => {
        // حتى لو فشل الطلب (شبكة، أو جلسة منتهية أصلاً) نُخرج المستخدم محلّياً.
        // إبقاؤه «داخلاً» في الواجهة بينما الخادم أخرجه أسوأ من الاثنين.
        try {
          await api.post('/auth/logout')
        } finally {
          resetCsrf()

          // ★ `queryClient.clear()` وحدها لا تُخطر المراقبين موثوقاً، فيبقى
          //   `me` قديماً حتى تحديث الصفحة. الكتابة الصريحة بـ null تُعيد
          //   التصيير فوراً، فيتكفّل <ProtectedRoute> بالتحويل إلى /login.
          queryClient.setQueryData(['me'], null)
          queryClient.removeQueries({ predicate: (q) => q.queryKey[0] !== 'me' })
        }
      },
    }),
    [me, isLoading, queryClient],
  )

  return <AuthContext value={value}>{children}</AuthContext>
}

export function useAuth(): AuthValue {
  const context = use(AuthContext)

  if (context === null) {
    throw new Error('useAuth يجب أن يُستدعى داخل <AuthProvider>.')
  }

  return context
}
