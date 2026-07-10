import { useCallback } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { candidateDetailQuery } from './queries'

/**
 * يجلب تفاصيل المرشّح عند **نيّة** الفتح (تمرير المؤشّر أو التركيز بالكيبورد).
 *
 * بين لحظة التمرير ولحظة النقر ~٢٠٠ms — وهي أطول من زمن الطلب. فحين تصل
 * النقرة تكون البيانات في الذاكرة، وتُرسم الصفحة بلا شبكةٍ ولا شاشة انتظار.
 *
 * `staleTime` هنا يمنع جلباً مكرّراً لو مرّ المؤشّر على البطاقة مرّاتٍ.
 */
export function usePrefetchCandidate(): (ref: string) => void {
  const client = useQueryClient()

  return useCallback(
    (ref: string) => {
      void client.prefetchQuery({ ...candidateDetailQuery(ref), staleTime: 60_000 })
    },
    [client],
  )
}
