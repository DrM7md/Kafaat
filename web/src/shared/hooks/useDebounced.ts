import { useEffect, useState } from 'react'

/**
 * يؤخّر انتشار القيمة — للبحث الفوري بلا إغراق الخادم بطلبٍ لكل حرف.
 */
export function useDebounced<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(timer)
  }, [value, delay])

  return debounced
}
