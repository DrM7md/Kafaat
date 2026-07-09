import { createContext, use, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ensureCsrfCookie } from '@/shared/api/client'
import type { Me, Permission } from '@/shared/api/types'

type AuthValue = {
  me: Me | null
  isLoading: boolean
  can: (permission: Permission) => boolean
  refresh: () => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()

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

  const value: AuthValue = {
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
      await api.post('/auth/logout')
      queryClient.clear()
    },
  }

  return <AuthContext value={value}>{children}</AuthContext>
}

export function useAuth(): AuthValue {
  const context = use(AuthContext)

  if (context === null) {
    throw new Error('useAuth يجب أن يُستدعى داخل <AuthProvider>.')
  }

  return context
}
