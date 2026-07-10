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
  type Column,
  type PageMeta,
  type SortState,
} from '@/shared/ui/DataTable'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { AdminToolbar, VerificationBadge } from './shared'
import { formatNumber } from '@/shared/lib/format'

export type Company = {
  id: number
  legal_name: string
  brand_name: string | null
  cr_number: string | null
  verification_status: 'unverified' | 'pending' | 'verified' | 'rejected'
  email: string
  contact_name: string
  account_status: string
  reveals_count: number
  completed_reveals_count: number
  created_at: string | null
}

const STATUS_FILTERS = [
  { value: '', label: 'كل الحالات' },
  { value: 'verified', label: 'موثّقة' },
  { value: 'pending', label: 'قيد التوثيق' },
  { value: 'unverified', label: 'غير موثّقة' },
  { value: 'rejected', label: 'مرفوضة' },
]

export function CompaniesPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = useAuth()

  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [sort, setSort] = useState<SortState>({ column: 'created_at', direction: 'desc' })
  const [pendingDelete, setPendingDelete] = useState<number[] | null>(null)

  const q = useDebounced(search)

  const { data, isPending, isFetching } = useQuery({
    queryKey: ['admin', 'companies', { q, status, page, sort }],
    queryFn: async () => {
      const params = { q, status, page, sort: sort.column, direction: sort.direction }
      return (await api.get<{ data: Company[]; meta: PageMeta }>('/admin/companies', { params })).data
    },
    placeholderData: keepPreviousData,
  })

  const verify = useMutation({
    mutationFn: async (company: Company) =>
      api.put(`/admin/companies/${company.id}/verification`, { status: 'verified' }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'companies'] }),
  })

  const remove = useMutation({
    mutationFn: async (ids: number[]) => api.delete('/admin/companies/bulk', { data: { ids } }),
    onSuccess: async () => {
      setPendingDelete(null)
      await queryClient.invalidateQueries({ queryKey: ['admin', 'companies'] })
    },
  })

  const columns: Column<Company>[] = [
    {
      key: 'legal_name',
      label: 'الشركة',
      sortable: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-semibold text-ink">{row.legal_name}</p>
          <p className="truncate text-[11px] text-[var(--k-text-muted)]" dir="ltr">
            {row.email}
          </p>
        </div>
      ),
    },
    {
      key: 'cr_number',
      label: 'السجلّ التجاري',
      render: (row) => (
        <code dir="ltr" className="text-[11px] text-[var(--k-text-secondary)]">
          {row.cr_number ?? '—'}
        </code>
      ),
    },
    {
      key: 'verification_status',
      label: 'التوثيق',
      sortable: true,
      render: (row) => <VerificationBadge status={row.verification_status} />,
    },
    {
      key: 'reveals_count',
      label: 'الطلبات',
      sortable: true,
      align: 'center',
      render: (row) => (
        <span className="numeric text-[13px]">
          <strong className="text-ink">{formatNumber(row.completed_reveals_count)}</strong>
          <span className="text-[var(--k-text-muted)]"> / {formatNumber(row.reveals_count)}</span>
        </span>
      ),
    },
    {
      key: 'created_at',
      label: 'التسجيل',
      sortable: true,
      render: (row) => (
        <span className="numeric text-[11px] text-[var(--k-text-muted)]">
          {row.created_at === null ? '—' : new Date(row.created_at).toLocaleDateString('ar-QA')}
        </span>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <header>
        <h1 className="font-heading text-2xl text-ink">الشركات</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          اضغط أي صفّ لفتح صفحة الشركة وطلبات الكشف الخاصّة بها.
        </p>
      </header>

      <AdminToolbar
        search={search}
        onSearch={(value) => {
          setSearch(value)
          setPage(1)
        }}
        placeholder="ابحث بالاسم أو السجلّ التجاري أو البريد…"
        filterValue={status}
        onFilter={(value) => {
          setStatus(value)
          setPage(1)
        }}
        filterOptions={STATUS_FILTERS}
        busy={isFetching && !isPending}
      />

      {remove.isError && (
        <p className="rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
          {apiErrorMessage(remove.error)}
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
        onRowClick={(row) => void navigate(`/admin/companies/${row.id}`)}
        bulkAction={
          can('user.manage')
            ? { label: 'حذف المحدّد', onConfirm: setPendingDelete, pending: remove.isPending }
            : undefined
        }
        rowActions={[
          {
            label: 'فتح الصفحة',
            icon: <EyeIcon />,
            onClick: (row) => void navigate(`/admin/companies/${row.id}`),
          },
          {
            label: 'توثيق',
            icon: <CheckCircleIcon />,
            hidden: (row) => row.verification_status === 'verified' || !can('company.approve'),
            onClick: (row) => verify.mutate(row),
          },
          {
            label: 'حذف',
            icon: <TrashIcon />,
            danger: true,
            hidden: () => !can('user.manage'),
            onClick: (row) => setPendingDelete([row.id]),
          },
        ]}
        emptyTitle="لا شركات مطابقة"
      />

      <ConfirmDialog
        open={pendingDelete !== null}
        danger
        pending={remove.isPending}
        title={`حذف ${formatNumber(pendingDelete?.length ?? 0)} شركة؟`}
        body="سيُعطَّل حسابها ولن تظهر في اللوحة. تبقى سجلّات الدفع والكشف كما هي — نزاهة الفواتير تسبق نظافة الجدول."
        confirmLabel="حذف"
        onCancel={() => setPendingDelete(null)}
        onConfirm={() => pendingDelete !== null && remove.mutate(pendingDelete)}
      />
    </div>
  )
}
