import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from 'react-router'
import { AuthProvider } from '@/shared/auth/AuthProvider'
import { router } from './router'

/**
 * إحساس «تطبيق سطح المكتب» مصدره أن العودة إلى شاشةٍ زُرتَ من قبل لا تُطلق
 * طلباً ولا تُظهر انتظاراً.
 *
 *   staleTime — كم تبقى البيانات «طازجة» فلا يُعاد جلبها عند العرض.
 *   gcTime    — كم تبقى في الذاكرة بعد أن تُغادر الشاشة. نصف ساعة تعني أن
 *               التنقّل ذهاباً وإياباً بين التصفّح والتفاصيل فوريّ دائماً.
 *
 * الطزاجة ليست خطراً هنا: كل طفرةٍ تُبطل مفاتيحها صراحةً (الكشف يُبطل
 * `['candidates']`، ولوحة المشرف تُبطل مفاتيحها)، فالبيانات القديمة لا تعمّر.
 */
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
      staleTime: 60_000,
      gcTime: 30 * 60_000,
    },
  },
})

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <RouterProvider router={router} />
      </AuthProvider>
    </QueryClientProvider>
  )
}
