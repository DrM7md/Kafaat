import { useState } from 'react'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { api, apiErrorMessage } from '@/shared/api/client'
import { useDebounced } from '@/shared/hooks/useDebounced'
import { useAuth } from '@/shared/auth/AuthProvider'
import {
  CheckCircleIcon,
  DataTable,
  EyeIcon,
  TrashIcon,
  XCircleIcon,
  type Column,
  type PageMeta,
  type SortState,
} from '@/shared/ui/DataTable'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { AdminToolbar, ApprovalBadge, CompletenessBar } from './shared'
import { formatNumber, formatRef, formatYears } from '@/shared/lib/format'

type Candidate = {
  id: number
  ref: string
  title: string
  field: string | null
  city: string | null
  years_experience: number
  employment_status: { label: string; meta: { bg: string; color: string; dot: string } | null } | null
  skills: string[]
  attachments_count: number
  completeness: number
  approval_status: 'draft' | 'pending' | 'approved' | 'rejected'
  rejection_reason: string | null
  submitted_at: string | null
}

type Response = {
  data: Candidate[]
  meta: PageMeta
  counts: Record<string, number>
}

const FILTERS = [
  { value: 'pending', label: 'بانتظار الاعتماد' },
  { value: '', label: 'كل الطلبات' },
  { value: 'approved', label: 'المعتمَدة' },
  { value: 'rejected', label: 'المرفوضة' },
  { value: 'draft', label: 'المسودّات' },
]

/**
 * طلبات اعتماد الكفاءات.
 *
 * ★ لا اسم ولا بريد ولا هاتف في هذا الجدول — الخادم لا يرسلها أصلاً.
 *   المراجعة تقع على جودة الملفّ، لا على هويّة صاحبه. من يحتاج رؤية الهوية
 *   يفتح صفحة المرشّح بصلاحية `profile.moderate`، ويُسجَّل عليه ذلك.
 */
export function CandidateApprovalsPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = useAuth()

  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('pending')
  const [page, setPage] = useState(1)
  const [sort, setSort] = useState<SortState>({ column: 'submitted_at', direction: 'desc' })
  const [rejecting, setRejecting] = useState<Candidate | null>(null)
  const [reason, setReason] = useState('')
  const [pendingDelete, setPendingDelete] = useState<number[] | null>(null)

  const q = useDebounced(search)

  const { data, isPending, isFetching } = useQuery({
    queryKey: ['admin', 'candidates', { q, status, page, sort }],
    queryFn: async () => {
      const params = { q, status, page, sort: sort.column, direction: sort.direction }
      return (await api.get<Response>('/admin/candidates', { params })).data
    },
    placeholderData: keepPreviousData,
  })

  async function refresh() {
    await queryClient.invalidateQueries({ queryKey: ['admin', 'candidates'] })
    await queryClient.invalidateQueries({ queryKey: ['candidates'] })
  }

  const approve = useMutation({
    mutationFn: async (row: Candidate) => api.post(`/admin/candidates/${row.ref}/approve`),
    onSuccess: refresh,
  })

  const reject = useMutation({
    mutationFn: async ({ row, why }: { row: Candidate; why: string }) =>
      api.post(`/admin/candidates/${row.ref}/reject`, { reason: why }),
    onSuccess: async () => {
      setRejecting(null)
      setReason('')
      await refresh()
    },
  })

  const remove = useMutation({
    mutationFn: async (ids: number[]) => api.delete('/admin/candidates/bulk', { data: { ids } }),
    onSuccess: async () => {
      setPendingDelete(null)
      await refresh()
    },
  })

  const columns: Column<Candidate>[] = [
    {
      key: 'title',
      label: 'الكفاءة',
      sortable: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-semibold text-ink">{row.title}</p>
          <code dir="ltr" className="text-[11px] text-[var(--k-text-muted)]">
            {formatRef(row.ref)}
          </code>
        </div>
      ),
    },
    {
      key: 'field',
      label: 'المجال',
      render: (row) => (
        <span className="text-[var(--k-text-secondary)]">
          {row.field ?? '—'}
          {row.city !== null && <span className="text-[var(--k-text-muted)]"> · {row.city}</span>}
        </span>
      ),
    },
    {
      key: 'years_experience',
      label: 'الخبرة',
      sortable: true,
      render: (row) => <span className="numeric text-[12px]">{formatYears(row.years_experience)}</span>,
    },
    {
      key: 'skills',
      label: 'المهارات',
      render: (row) => (
        <div className="flex flex-wrap gap-1">
          {row.skills.slice(0, 3).map((skill) => (
            <span key={skill} className="rounded bg-canvas px-1.5 py-0.5 text-[10px] text-[var(--k-text-secondary)]">
              {skill}
            </span>
          ))}
          {row.skills.length > 3 && (
            <span className="numeric text-[10px] text-[var(--k-text-muted)]">+{row.skills.length - 3}</span>
          )}
        </div>
      ),
    },
    {
      key: 'completeness',
      label: 'الاكتمال',
      sortable: true,
      render: (row) => <CompletenessBar value={row.completeness} />,
    },
    {
      key: 'approval_status',
      label: 'الحالة',
      render: (row) => (
        <div className="flex flex-col gap-1">
          <ApprovalBadge status={row.approval_status} />
          {row.rejection_reason !== null && (
            <span className="max-w-[160px] truncate text-[10px] text-[var(--k-text-muted)]" title={row.rejection_reason}>
              {row.rejection_reason}
            </span>
          )}
        </div>
      ),
    },
    {
      key: 'submitted_at',
      label: 'تاريخ التقديم',
      sortable: true,
      render: (row) => (
        <span className="numeric text-[11px] text-[var(--k-text-muted)]">
          {row.submitted_at === null ? '—' : new Date(row.submitted_at).toLocaleDateString('ar-QA')}
        </span>
      ),
    },
  ]

  const counts = data?.counts ?? {}

  return (
    <div className="flex flex-col gap-5">
      <header>
        <h1 className="font-heading text-2xl text-ink">طلبات الكفاءات</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          الملفّ لا يظهر لأي شركة قبل اعتماده. المراجعة على جودة الملفّ — الهويّة محجوبة عنك هنا عمداً.
        </p>
      </header>

      <div className="grid gap-3 sm:grid-cols-4">
        <Counter label="بانتظار الاعتماد" value={counts.pending ?? 0} highlight />
        <Counter label="معتمَدة" value={counts.approved ?? 0} />
        <Counter label="مرفوضة" value={counts.rejected ?? 0} />
        <Counter label="مسودّات" value={counts.draft ?? 0} />
      </div>

      <AdminToolbar
        search={search}
        onSearch={(value) => {
          setSearch(value)
          setPage(1)
        }}
        placeholder="ابحث بالمسمّى أو المهارة أو المعرّف…"
        filterValue={status}
        onFilter={(value) => {
          setStatus(value)
          setPage(1)
        }}
        filterOptions={FILTERS}
        busy={isFetching && !isPending}
      />

      {(approve.isError || reject.isError || remove.isError) && (
        <p className="rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
          {apiErrorMessage(approve.error ?? reject.error ?? remove.error)}
        </p>
      )}

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        meta={data?.meta}
        sort={sort}
        onSortChange={(next) => {
          setSort(next)
          setPage(1)
        }}
        onPageChange={setPage}
        isLoading={isPending}
        bulkAction={
          can('user.manage')
            ? { label: 'حذف المحدّد', onConfirm: setPendingDelete, pending: remove.isPending }
            : undefined
        }
        rowActions={[
          {
            label: 'فتح الملفّ',
            icon: <EyeIcon />,
            // فتح الملفّ يكشف الهوية ويُسجَّل في التدقيق — لمن يملك profile.moderate.
            hidden: () => !can('profile.moderate'),
            onClick: (row) => void navigate(`/candidates/${row.ref}`),
          },
          {
            label: 'اعتماد',
            icon: <CheckCircleIcon />,
            hidden: (row) => row.approval_status === 'approved' || !can('profile.approve'),
            onClick: (row) => approve.mutate(row),
          },
          {
            label: 'رفض',
            icon: <XCircleIcon />,
            danger: true,
            hidden: (row) => row.approval_status === 'rejected' || !can('profile.approve'),
            onClick: setRejecting,
          },
          {
            label: 'حذف',
            icon: <TrashIcon />,
            danger: true,
            hidden: () => !can('user.manage'),
            onClick: (row) => setPendingDelete([row.id]),
          },
        ]}
        emptyTitle={status === 'pending' ? 'لا طلبات تنتظر الاعتماد' : 'لا نتائج'}
        emptyBody={status === 'pending' ? 'كل الطلبات المقدَّمة روجعت.' : 'جرّب تعديل البحث أو الفلتر.'}
      />

      <ConfirmDialog
        open={rejecting !== null}
        danger
        pending={reject.isPending}
        title="رفض الطلب"
        body="سيُبلَّغ صاحب الملفّ بالسبب، ولن يظهر ملفّه للشركات."
        confirmLabel="رفض"
        onCancel={() => {
          setRejecting(null)
          setReason('')
        }}
        onConfirm={() => rejecting !== null && reason.trim().length >= 5 && reject.mutate({ row: rejecting, why: reason })}
      >
        <label className="flex flex-col gap-1.5">
          <span className="text-[13px] font-medium text-ink">سبب الرفض</span>
          <textarea
            rows={3}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="مثال: النبذة غير كافية، والسيرة الذاتية مفقودة."
            className="rounded-xl border border-[var(--k-border-strong)] bg-surface px-3.5 py-2.5 text-sm outline-none focus:border-primary"
          />
          {reason.trim().length > 0 && reason.trim().length < 5 && (
            <span className="text-[11px] text-[#8a2b2b]">السبب قصير جداً.</span>
          )}
        </label>
      </ConfirmDialog>

      <ConfirmDialog
        open={pendingDelete !== null}
        danger
        pending={remove.isPending}
        title={`حذف ${formatNumber(pendingDelete?.length ?? 0)} ملفّ؟`}
        body="سيُخفى الملفّ من المنصّة. تبقى سجلّات الكشف والدفع المرتبطة به كما هي."
        confirmLabel="حذف"
        onCancel={() => setPendingDelete(null)}
        onConfirm={() => pendingDelete !== null && remove.mutate(pendingDelete)}
      />
    </div>
  )
}

function Counter({ label, value, highlight = false }: { label: string; value: number; highlight?: boolean }) {
  return (
    <div
      className={`rounded-[16px] border p-4 ${
        highlight && value > 0
          ? 'border-[rgb(180_134_59/0.35)] bg-gold-tint'
          : 'border-[var(--k-border)] bg-surface'
      }`}
    >
      <p className="text-[11px] text-[var(--k-text-muted)]">{label}</p>
      <p className={`numeric mt-1 text-xl font-bold ${highlight && value > 0 ? 'text-[#96682A]' : 'text-ink'}`}>
        {formatNumber(value)}
      </p>
    </div>
  )
}
