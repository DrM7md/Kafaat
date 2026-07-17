import { useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '@/shared/api/client'
import type { Candidate, Job, JobSlots, Paginated } from '@/shared/api/types'

// ── جانب الموظّف ──

export function jobBoardQuery(filters: { q: string }) {
  return {
    queryKey: ['jobs', 'board', filters] as const,
    queryFn: async () =>
      (await api.get<Paginated<Job>>('/jobs', { params: filters })).data,
  }
}

export function jobDetailQuery(ref: string) {
  return {
    queryKey: ['jobs', 'detail', ref] as const,
    queryFn: async () => (await api.get<{ job: Job }>(`/jobs/${ref}`)).data.job,
    enabled: ref !== '',
  }
}

/**
 * إبداء الإعجاب.
 *
 * ★★ لا يكشف هويّتك. الشركة تُبلَّغ بأنّ «مرشّحاً» مهتمّ، وترى بطاقتك محجوبةً
 *    وزرَّ كشف — تدفع رسمَه إن أرادت التواصل معك.
 */
export function useJobInterest() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (v: { ref: string; interested: boolean }) =>
      v.interested
        ? api.post(`/jobs/${v.ref}/interest`)
        : api.delete(`/jobs/${v.ref}/interest`),

    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['jobs'] })
    },
  })
}

// ── جانب الشركة ──

type MyJobsResponse = { data: Job[]; slots: JobSlots }

export function myJobsQuery() {
  return {
    queryKey: ['jobs', 'mine'] as const,
    queryFn: async () => (await api.get<MyJobsResponse>('/my-jobs')).data,
  }
}

export function jobInterestedQuery(ref: string) {
  return {
    queryKey: ['jobs', 'interested', ref] as const,
    queryFn: async () =>
      (await api.get<{ job: { ref: string; title: string }; data: Candidate[] }>(
        `/my-jobs/${ref}/interested`,
      )).data,
    enabled: ref !== '',
  }
}

export function useMyJobMutations() {
  const queryClient = useQueryClient()

  const refresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['jobs'] })
  }

  return {
    create: useMutation({
      mutationFn: async (body: Record<string, unknown>) => api.post('/my-jobs', body),
      onSuccess: refresh,
    }),

    update: useMutation({
      mutationFn: async (v: { ref: string; body: Record<string, unknown> }) =>
        api.put(`/my-jobs/${v.ref}`, v.body),
      onSuccess: refresh,
    }),

    // ★ 402 هنا تعني «استنفدتَ حصّتك» — لا خطأً. الصفحة تعرض شراء خانة.
    open: useMutation({
      mutationFn: async (ref: string) => api.post(`/my-jobs/${ref}/open`),
      onSuccess: refresh,
    }),

    close: useMutation({
      mutationFn: async (ref: string) => api.post(`/my-jobs/${ref}/close`),
      onSuccess: refresh,
    }),

    remove: useMutation({
      mutationFn: async (ref: string) => api.delete(`/my-jobs/${ref}`),
      onSuccess: refresh,
    }),
  }
}

export function usePurchaseSlot() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () =>
      api.post<{ payment_url: string; amount: number }>(
        '/job-slots/purchase',
        {},
        // مفتاح التعطيل إلزامي: بلا هذا يُنشئ ضغطان متتاليان دفعتين.
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      ),

    onSuccess: async (response) => {
      await queryClient.invalidateQueries({ queryKey: ['jobs'] })

      // البوّابة تستضيف صفحة الدفع — لا نجمع بيانات بطاقةٍ أبداً (PCI-DSS).
      window.location.href = response.data.payment_url
    },
  })
}
