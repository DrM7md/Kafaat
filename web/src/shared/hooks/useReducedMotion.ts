import { useSyncExternalStore } from 'react'

/**
 * هل يفضّل المستخدم حركةً مخفّضة؟ (إعداد نظام التشغيل)
 *
 * الحركات الزخرفية — دفع البطاقة، مربّعات الخلفية، اللمعات — تُطفأ حين يكون
 * الجواب `true`. لا نكتفي بـ `@media (prefers-reduced-motion)` في CSS لأنّ
 * الحركة هنا يحرّكها JS (زوايا الدفع، حلقة الرسم)، فنحتاج القيمة في React.
 *
 * `useSyncExternalStore` يبقيها متزامنةً إن غيّر المستخدم التفضيل أثناء الجلسة.
 */
const QUERY = '(prefers-reduced-motion: reduce)'

function subscribe(callback: () => void): () => void {
  if (typeof window === 'undefined' || !window.matchMedia) return () => {}

  const mql = window.matchMedia(QUERY)
  mql.addEventListener('change', callback)
  return () => mql.removeEventListener('change', callback)
}

function getSnapshot(): boolean {
  if (typeof window === 'undefined' || !window.matchMedia) return false
  return window.matchMedia(QUERY).matches
}

export function useReducedMotion(): boolean {
  return useSyncExternalStore(subscribe, getSnapshot, () => false)
}
