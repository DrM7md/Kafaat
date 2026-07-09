import { useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/shared/api/client'

type RevealResponse =
  | { payment_url: string; reveal_id: number; message: string }
  | { data: { name?: string } }

/**
 * تنفيذ الكشف.
 *
 * `Idempotency-Key` إلزامي: بلا هذا، ضغطةٌ مزدوجة على الزرّ أو إعادة إرسالٍ
 * من الشبكة تُنشئ دفعتين. المفتاح ثابت لكل (شركة، مرشّح) في هذه الجلسة.
 *
 * الاستجابات المتوقّعة:
 *   200 — كشفٌ سابق صالح، لا خصم.
 *   201 — تمّ الكشف فوراً (اشتراك/منحة).
 *   202 — بانتظار الدفع ⇒ يُعاد التوجيه إلى صفحة البوّابة.
 *   402/409/404 — راجع Brain/07-api-contract.md §2.
 */
export function useReveal() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (ref: string): Promise<RevealResponse> => {
      const response = await api.post<RevealResponse>(
        `/candidates/${ref}/reveal`,
        {},
        { headers: { 'Idempotency-Key': idempotencyKeyFor(ref) } },
      )

      return response.data
    },

    onSuccess: async (data) => {
      if ('payment_url' in data) {
        window.location.href = data.payment_url
        return
      }

      // فُكّ الحجب: أعِد جلب القوائم لتظهر البيانات المكشوفة.
      await queryClient.invalidateQueries({ queryKey: ['candidates'] })
    },
  })
}

/**
 * مفتاح ثابت لكل زوج (متصفّح، مرشّح) — يبقى عبر إعادة تحميل الصفحة.
 * لا يُستعمل لتخزين أسرار: مجرّد معرّف طلبٍ لمنع التكرار.
 */
function idempotencyKeyFor(ref: string): string {
  const storageKey = `kafaat.reveal.${ref}`
  const existing = sessionStorage.getItem(storageKey)

  if (existing !== null) return existing

  const key = crypto.randomUUID()
  sessionStorage.setItem(storageKey, key)

  return key
}
