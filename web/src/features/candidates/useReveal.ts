import { useMutation, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { api } from '@/shared/api/client'

type RevealBody = {
  message?: string
  payment_url?: string
  reveal_id?: number
  data?: { name?: string }
}

/** ناتج الكشف بعد التطبيع من حالة HTTP لا من وجود مفتاحٍ في الجسم. */
type RevealResult = { kind: 'revealed' } | { kind: 'awaiting'; paymentUrl: string }

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
    mutationFn: async (ref: string): Promise<RevealResult> => {
      const response = await api.post<RevealBody>(
        `/candidates/${ref}/reveal`,
        {},
        { headers: { 'Idempotency-Key': idempotencyKeyFor(ref) } },
      )

      // بانتظار الدفع: نُميّزه بحالة 202 لا بوجود مفتاحٍ في الجسم. نُبقي مفتاح
      // التكرار كي تصل إعادة الإرسال إلى الدفعة المعلّقة نفسها لا إلى جديدة.
      if (response.status === 202) {
        const url = response.data.payment_url
        if (url === undefined || url === '') {
          throw new Error('استجابة دفعٍ ناقصة: لا رابط بوّابة.')
        }
        if (!isAllowedRedirect(url)) {
          throw new Error('رابط دفعٍ غير موثوق — أُلغيت إعادة التوجيه.')
        }
        return { kind: 'awaiting', paymentUrl: url }
      }

      // اكتمل الكشف: امسح المفتاح كي يُسمح بكشفٍ جديد بعد استرداد سابق —
      // إعادةُ إرسال المفتاح القديم تجعل المحاولة الجديدة no-op صامتاً.
      clearIdempotencyKey(ref)
      return { kind: 'revealed' }
    },

    onSuccess: async (result) => {
      if (result.kind === 'awaiting') {
        window.location.href = result.paymentUrl
        return
      }

      // فُكّ الحجب: أعِد جلب القوائم لتظهر البيانات المكشوفة.
      await queryClient.invalidateQueries({ queryKey: ['candidates'] })
    },

    onError: (error, ref) => {
      // 402/409 نهائيان: امسح المفتاح كي لا تصير إعادة المحاولة no-op صامتاً.
      const status = axios.isAxiosError(error) ? error.response?.status : undefined
      if (status === 402 || status === 409) {
        clearIdempotencyKey(ref)
      }
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

function clearIdempotencyKey(ref: string): void {
  sessionStorage.removeItem(`kafaat.reveal.${ref}`)
}

/** تحصين ضدّ إعادة التوجيه المفتوح: لا نتبع إلا أصلنا أو أصل الـ API. */
function isAllowedRedirect(url: string): boolean {
  try {
    const target = new URL(url, window.location.origin)
    const allowed = new Set([window.location.origin])
    const apiUrl = import.meta.env.VITE_API_URL
    if (typeof apiUrl === 'string' && apiUrl !== '') {
      allowed.add(new URL(apiUrl).origin)
    }
    return allowed.has(target.origin)
  } catch {
    return false
  }
}
