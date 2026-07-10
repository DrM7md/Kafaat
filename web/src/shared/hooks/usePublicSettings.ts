import { useQuery } from '@tanstack/react-query'
import { api } from '@/shared/api/client'

/** الإعدادات العامّة (بلا مصادقة) — GET /settings/public ⇒ { settings: {…} }. */
export function usePublicSettings() {
  return useQuery({
    queryKey: ['settings', 'public'],
    queryFn: async () =>
      (await api.get<{ settings: Record<string, unknown> }>('/settings/public')).data.settings,
    staleTime: 5 * 60_000,
  })
}

/**
 * هل الدفع قيد التطوير؟ المفتاح `billing.under_development` افتراضه `true`
 * في الخلفية، فنعتبره كذلك ما لم يصل صريحاً بقيمة `false`.
 */
export function useBillingUnderDevelopment(): boolean {
  const { data } = usePublicSettings()
  return data?.['billing.under_development'] !== false
}
