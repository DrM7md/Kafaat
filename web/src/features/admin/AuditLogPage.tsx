import { useQuery } from '@tanstack/react-query'
import { api } from '@/shared/api/client'

type AuditLog = {
  id: number
  action: string
  actor: { id: number; name: string; email: string } | null
  subject_type: string | null
  subject_id: string | null
  ip: string | null
  meta: Record<string, unknown> | null
  created_at: string
}

const ACTION_LABELS: Record<string, string> = {
  'candidate.revealed': 'كشف مرشّح',
  'candidate.reveal_granted': 'منحة كشف',
  'payment.refunded': 'استرداد',
  'profile.viewed_as_moderator': 'اطّلاع إشرافي على ملفّ',
  'role.permissions_updated': 'تعديل صلاحيات دور',
  'settings.updated': 'تعديل إعداد',
  'user.data_exported': 'تصدير بيانات',
  'user.anonymized': 'إخفاء هوية حساب',
}

/** الصلاحيات الحرجة التي يجب أن يلفت أثرُها النظر. */
const CRITICAL = new Set([
  'profile.viewed_as_moderator',
  'role.permissions_updated',
  'settings.updated',
  'payment.refunded',
  'candidate.reveal_granted',
])

export function AuditLogPage() {
  const { data, isPending, isError } = useQuery({
    queryKey: ['admin', 'audit'],
    queryFn: async () => (await api.get<{ data: AuditLog[] }>('/admin/audit-logs')).data.data,
  })

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="font-heading text-2xl text-ink">سجلّ التدقيق</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          سجلّ إلحاقيّ: لا يُعدَّل ولا يُحذَف. كل كشفٍ ودفعٍ وتعديل إعدادٍ وممارسةِ صلاحيةٍ حرجة له أثرٌ هنا.
        </p>
      </header>

      {isPending && <p className="text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>}

      {isError && (
        <div className="grid place-items-center rounded-[16px] border border-dashed border-[var(--k-border-strong)] p-12 text-center">
          <p className="font-heading text-lg text-ink">تعذّر تحميل السجلّ</p>
          <p className="mt-1 text-sm text-[var(--k-text-secondary)]">تأكّد من الاتصال ثم أعد المحاولة.</p>
        </div>
      )}

      {data !== undefined && data.length === 0 && (
        <p className="rounded-[16px] border border-dashed border-[var(--k-border-strong)] p-12 text-center text-sm text-[var(--k-text-muted)]">
          لا أحداث بعد.
        </p>
      )}

      {data !== undefined && data.length > 0 && (
        <div className="overflow-x-auto rounded-[20px] border border-[var(--k-border)] bg-surface">
          <table className="w-full text-right text-[13px]">
            <thead className="bg-surface-muted text-[11px] text-[var(--k-text-muted)]">
              <tr>
                <th className="p-3 font-medium">الفعل</th>
                <th className="p-3 font-medium">الفاعل</th>
                <th className="p-3 font-medium">الكائن</th>
                <th className="p-3 font-medium">العنوان</th>
                <th className="p-3 font-medium">الوقت</th>
              </tr>
            </thead>
            <tbody>
              {data.map((log) => (
                <tr key={log.id} className="border-t border-[var(--k-divider)]">
                  <td className="p-3">
                    <span className="inline-flex items-center gap-1.5">
                      {CRITICAL.has(log.action) && <span title="فعل حرج">🔴</span>}
                      <span className="font-medium text-ink">{ACTION_LABELS[log.action] ?? log.action}</span>
                    </span>
                  </td>
                  <td className="p-3 text-[var(--k-text-secondary)]">{log.actor?.name ?? 'النظام'}</td>
                  <td className="p-3">
                    <code dir="ltr" className="text-[11px] text-[var(--k-text-muted)]">
                      {log.subject_type?.split('\\').pop() ?? '—'}
                      {log.subject_id !== null && ` #${log.subject_id}`}
                    </code>
                  </td>
                  <td className="p-3">
                    <code dir="ltr" className="text-[11px] text-[var(--k-text-muted)]">
                      {log.ip ?? '—'}
                    </code>
                  </td>
                  <td className="p-3 numeric text-[11px] text-[var(--k-text-muted)]">
                    {new Date(log.created_at).toLocaleString('ar-QA-u-nu-latn')}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
