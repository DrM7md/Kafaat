import { queryOptions, type QueryClient } from '@tanstack/react-query'
import { api } from '@/shared/api/client'
import type { Candidate, Lookup, Paginated } from '@/shared/api/types'

/**
 * تعريفٌ واحد للاستعلام: يستعمله العرض والجلب المسبق معاً.
 *
 * لو عرّفنا `queryKey` مرّتين لانحرفا يوماً، فيجلب المؤشّر بيانات مفتاحٍ
 * ويقرأ العرض من مفتاحٍ آخر — وتبقى شاشة الانتظار كما هي بلا سبب ظاهر.
 */
export function candidateDetailQuery(ref: string) {
  return queryOptions({
    queryKey: ['candidates', 'detail', ref],
    queryFn: async () => (await api.get<{ data: Candidate }>(`/candidates/${ref}`)).data.data,
  })
}

export type Filters = {
  q: string
  field: string
  city: string
  status: string
  min_experience: string
}

/**
 * ★ يجب أن يبقى هذا الكائن **مطابقاً** لحالة البدء في `BrowsePage`.
 *   React Query يجزّئ المفتاح ببنيته، فأي اختلافٍ يعني أن التسخين المسبق
 *   يملأ مفتاحاً والصفحة تقرأ من آخر — فيعود الانتظار بلا سبب ظاهر.
 */
export const EMPTY_FILTERS: Filters = { q: '', field: '', city: '', status: '', min_experience: '' }

export function candidateListQuery(filters: Filters) {
  return queryOptions({
    queryKey: ['candidates', 'list', filters],
    queryFn: async () => {
      const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''))
      return (await api.get<Paginated<Candidate>>('/candidates', { params })).data
    },
  })
}

export function lookupsQuery() {
  return queryOptions({
    queryKey: ['lookups'],
    queryFn: async () => (await api.get<{ lookups: Record<string, Lookup[]> }>('/lookups')).data.lookups,
    staleTime: Infinity,
  })
}

/**
 * يبحث عن المرشّح في قوائم التصفّح المخزَّنة.
 *
 * ★ بطاقة القائمة تحمل أغلب ما تعرضه صفحة التفاصيل (المسمّى، المدينة،
 *   الخبرة، المهارات، الراتب، حالة الكشف). فنرسم الصفحة **فوراً** منها،
 *   ثم يصل الطلب الحقيقي في الخلفية فيُكمل النبذة والخبرات.
 *
 *   الحقول المحجوبة لا تُخترَع هنا: البطاقة نفسها لا تحملها إن لم تُكشَف.
 */
export function findCandidateInLists(client: QueryClient, ref: string): Candidate | undefined {
  for (const [, cached] of client.getQueriesData({ queryKey: ['candidates', 'list'] })) {
    const page = cached as Paginated<Candidate> | undefined

    if (!Array.isArray(page?.data)) continue

    const hit = page.data.find((candidate) => candidate.ref === ref)
    if (hit !== undefined) return hit
  }

  return undefined
}
