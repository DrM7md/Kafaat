import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/shared/api/client'
import { useAuth } from '@/shared/auth/AuthProvider'
import type { Permission } from '@/shared/api/types'

export type NotificationKey =
  | 'profile.revealed'
  | 'profile.approved'
  | 'profile.rejected'
  | 'reveal.completed'
  | 'reveal.refunded'
  | 'company.verified'
  | 'admin.profile_submitted'
  | 'admin.company_registered'
  | 'admin.company_reverify'
  | 'admin.lookup_suggested'
  | 'admin.reconciliation_required'

export type AppNotification = {
  id: string
  key: NotificationKey | 'unknown'
  title: string
  body: string
  /** مسار الواجهة الذي يفتحه النقر. `null` = إشعارٌ إخباري لا وجهةَ له. */
  link: string | null
  meta: Record<string, string | number | null>
  /** ليس فارغاً ⇒ «تمّ» — تابعه المستخدم بالفعل. */
  read_at: string | null
  created_at: string | null
}

export type Tab = 'all' | 'new' | 'done'

/**
 * الصلاحيات التي تُنتج إشعاراً يستدعي **فعلاً** من صاحبه.
 *
 * من يملك واحدةً منها يرى صفحة `/notifications` بتبويبتَي «جديد/تمّ»: صندوقه
 * طابور عمل، فيحتاج أن يعرف ما تابعه وما بقي. أمّا الموظّف والشركة فصندوقهما
 * هو **قائمة الجرس** — خبرٌ يُقرأ ويُنقر ثمّ يغادر، وصفحتهما أرشيفُ ما قرأوه.
 */
const STAFF_PERMISSIONS: Permission[] = [
  'profile.approve',
  'company.approve',
  'lookup.manage',
  'payment.view',
  'audit.view',
]

export function useTracksFollowUp(): boolean {
  const { can } = useAuth()

  return STAFF_PERMISSIONS.some((permission) => can(permission))
}

export type NotificationsResponse = {
  data: AppNotification[]
  meta: { current_page: number; last_page: number; total: number }
  unread_count: number
}

export function notificationsQuery(filter: Tab) {
  return {
    queryKey: ['notifications', filter] as const,
    queryFn: async () =>
      (await api.get<NotificationsResponse>('/notifications', { params: { filter } })).data,
  }
}

/** مفتاح قائمة الجرس — تُعدَّل تفاؤلياً عند التعليم كمقروء. */
const INBOX_KEY = ['notifications', 'new'] as const

/**
 * قائمة الجرس: **الجديد وحده**.
 *
 * ما يُفتح يغادر القائمة، فلا يبقى فيها إلّا ما لم يره صاحبه بعد — والعدّاد
 * على الجرس هو طول هذه القائمة نفسها، فطلبٌ واحد يخدم الرقمَ والقائمة معاً.
 *
 * و`refetchInterval` لأنّ الإشعار قد يصل بفعل غيرِ المستخدم (webhook الدفع،
 * قرار مشرف) — فلا شيء يُبطل الذاكرة تلقائياً.
 */
export function useInbox() {
  return useQuery({
    ...notificationsQuery('new'),
    refetchInterval: 60_000,
    refetchOnWindowFocus: true,
  })
}

/**
 * تعليمُ إشعارٍ مقروءاً.
 *
 * ★ التحديث **تفاؤلي**: النقرة تُغلق القائمة وتنقل المستخدم إلى وجهة الإشعار،
 *   فلو انتظرنا ردّ الخادم لرآه ما زال في «الجديد» حين يعود. يختفي فوراً،
 *   ويُستردّ إن أخفق الطلب.
 */
export function useMarkRead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: string) => api.post(`/notifications/${id}/read`),

    onMutate: async (id: string) => {
      await queryClient.cancelQueries({ queryKey: INBOX_KEY })

      const previous = queryClient.getQueryData<NotificationsResponse>(INBOX_KEY)

      queryClient.setQueryData<NotificationsResponse>(INBOX_KEY, (old) =>
        old === undefined
          ? old
          : {
              ...old,
              data: old.data.filter((notification) => notification.id !== id),
              unread_count: Math.max(0, old.unread_count - 1),
            },
      )

      return { previous }
    },

    onError: (_error, _id, context) => {
      if (context?.previous !== undefined) {
        queryClient.setQueryData(INBOX_KEY, context.previous)
      }
    },

    // يشمل «تمّ» و«المقروءة» أيضاً — الإشعار انتقل إليهما.
    onSettled: async () => {
      await queryClient.invalidateQueries({ queryKey: ['notifications'] })
    },
  })
}

export function useMarkAllRead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => api.post('/notifications/read-all'),

    onMutate: async () => {
      await queryClient.cancelQueries({ queryKey: INBOX_KEY })

      const previous = queryClient.getQueryData<NotificationsResponse>(INBOX_KEY)

      queryClient.setQueryData<NotificationsResponse>(INBOX_KEY, (old) =>
        old === undefined ? old : { ...old, data: [], unread_count: 0 },
      )

      return { previous }
    },

    onError: (_error, _variables, context) => {
      if (context?.previous !== undefined) {
        queryClient.setQueryData(INBOX_KEY, context.previous)
      }
    },

    onSettled: async () => {
      await queryClient.invalidateQueries({ queryKey: ['notifications'] })
    },
  })
}
