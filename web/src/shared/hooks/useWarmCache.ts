import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { candidateListQuery, lookupsQuery, EMPTY_FILTERS } from '@/features/candidates/queries'
import { useAuth } from '@/shared/auth/AuthProvider'

/**
 * يُسخّن ما سيُطلَب حتماً، لحظةَ دخول المستخدم — لا لحظةَ نقره.
 *
 * ★ المستخدم يقضي ثوانيَ في قراءة الشريط العلوي قبل أن يضغط «تصفّح
 *   الكفاءات». تلك الثواني مجّانية: نملأ فيها القوائم المرجعية والصفحة
 *   الأولى، فتُرسم الشاشة عند النقر من الذاكرة بلا طلبٍ ولا انتظار.
 *
 * `prefetchQuery` لا يرمي عند الفشل ولا يُصيّر شيئاً — أسوأ ما يقع أن
 * تسلك الشاشة سلوكها القديم.
 */
export function useWarmCache(): void {
  const client = useQueryClient()
  const { me } = useAuth()

  useEffect(() => {
    if (me === null) return

    void client.prefetchQuery(lookupsQuery())

    if (me.permissions.includes('candidate.browse')) {
      void client.prefetchQuery(candidateListQuery(EMPTY_FILTERS))
    }
  }, [me, client])
}
