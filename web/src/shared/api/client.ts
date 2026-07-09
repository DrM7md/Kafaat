import axios from 'axios'

const BASE = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

/**
 * ADR-002 — تُصادَق الواجهة بجلسة كوكيز httpOnly، لا بتوكن في localStorage.
 * لذلك: withCredentials، وجلب كوكي CSRF قبل أي طلب يغيّر الحالة.
 *
 * لا يُخزَّن هنا أي توكن ولا بيانات مستخدم. الخادم هو الحقيقة.
 */
export const api = axios.create({
  baseURL: `${BASE}/api/v1`,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

let csrfReady = false

/** يجلب كوكي XSRF مرّة واحدة لكل جلسة متصفّح. */
export async function ensureCsrfCookie(): Promise<void> {
  if (csrfReady) return
  await axios.get(`${BASE}/sanctum/csrf-cookie`, { withCredentials: true })
  csrfReady = true
}

api.interceptors.request.use(async (config) => {
  const method = config.method?.toUpperCase() ?? 'GET'
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    await ensureCsrfCookie()
  }
  return config
})

export type ApiError = {
  message: string
  error?: string
  errors?: Record<string, string[]>
}

export function apiErrorMessage(err: unknown): string {
  if (axios.isAxiosError<ApiError>(err)) {
    return err.response?.data?.message ?? 'تعذّر الاتصال بالخادم.'
  }
  return 'حدث خطأ غير متوقّع.'
}
