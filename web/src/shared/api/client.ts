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

let csrfPromise: Promise<void> | null = null

/**
 * يجلب كوكي XSRF مرّة واحدة لكل جلسة متصفّح.
 *
 * نحفظ الوعد نفسه لا علماً منطقياً: لو انطلقت أول عمليتَي تغييرٍ معاً، فكلتاهما
 * تنتظران الجلب ذاته بدل أن تجلب كلٌّ منهما `/sanctum/csrf-cookie` على حدة.
 */
export function ensureCsrfCookie(): Promise<void> {
  csrfPromise ??= axios
    .get(`${BASE}/sanctum/csrf-cookie`, { withCredentials: true })
    .then(() => undefined)
    .catch((err: unknown) => {
      csrfPromise = null // اسمح بإعادة المحاولة بعد فشل الجلب.
      throw err
    })
  return csrfPromise
}

/**
 * يُبطل علم الـ CSRF بعد الخروج.
 *
 * الخادم يُبطل الجلسة ويولّد رمزاً جديداً (`regenerateToken`)، فالرمز
 * المحفوظ في المتصفّح يصبح بائتاً. بلا هذا، أول تسجيل دخولٍ تالٍ يُردّ
 * بـ 419 لأننا لن نجلب كوكياً جديداً.
 */
export function resetCsrf(): void {
  csrfPromise = null
}

/**
 * خطّاف يُستدعى عند 401 غير متوقّع (جلسة انتهت على الخادم بينما تظنّها الواجهة
 * حيّة). يوصله `AuthProvider` بمسح ذاكرة المصادقة. لا نستورد `queryClient`
 * هنا: ذلك يصنع استيراداً دائرياً (App ⇐ router ⇐ pages ⇐ client).
 */
let onUnauthorized: (() => void) | null = null

export function registerUnauthorizedHandler(handler: () => void): void {
  onUnauthorized = handler
}

/** مسارات لا يُشغَّل عليها الخطّاف: 401 عليها متوقّع، وتشغيله يصنع حلقة. */
const AUTH_PATHS = ['/auth/login', '/auth/logout', '/me']

api.interceptors.request.use(async (config) => {
  const method = config.method?.toUpperCase() ?? 'GET'
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    await ensureCsrfCookie()
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      const url = error.config?.url ?? ''
      if (!AUTH_PATHS.some((path) => url.startsWith(path))) {
        onUnauthorized?.()
      }
    }
    return Promise.reject(error)
  },
)

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
