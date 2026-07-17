import { useState, type ReactNode } from 'react'
import { formatNumber } from '@/shared/lib/format'

export type Column<T> = {
  key: string
  label: string
  /** هل يقبل الخادم الفرز بهذا العمود؟ (allowlist في HandlesDataTable) */
  sortable?: boolean
  align?: 'start' | 'center' | 'end'
  render: (row: T) => ReactNode
}

export type SortState = { column: string; direction: 'asc' | 'desc' }

export type PageMeta = {
  total: number
  current_page: number
  last_page: number
  per_page: number
}

export type RowAction<T> = {
  label: string
  icon: ReactNode
  onClick: (row: T) => void
  /** يُخفى الفعل لصفوفٍ بعينها (مثلاً: لا اعتماد لملفٍّ معتمَد). */
  hidden?: (row: T) => boolean
  danger?: boolean
}

/**
 * جدول لوحة المشرف — مكوّن واحد يخدم كل الجداول.
 *
 * الفرز والترقيم والبحث كلّها **من الخادم**: الترتيب على صفحةٍ واحدة يكذب
 * حين تكون النتائج أكثر من صفحة. التحديد الجماعي يقتصر على الصفحة الظاهرة
 * لهذا السبب نفسه — «تحديد الكل» تعني «كل ما تراه».
 */
export function DataTable<T extends { id: number }>({
  columns,
  rows,
  meta,
  sort,
  onSortChange,
  onPageChange,
  isLoading = false,
  rowActions = [],
  bulkAction,
  onRowClick,
  emptyTitle = 'لا نتائج',
  emptyBody = 'جرّب تعديل البحث أو الفلاتر.',
}: {
  columns: Column<T>[]
  rows: T[]
  meta?: PageMeta
  sort: SortState
  onSortChange: (sort: SortState) => void
  onPageChange: (page: number) => void
  isLoading?: boolean
  rowActions?: RowAction<T>[]
  bulkAction?: { label: string; onConfirm: (ids: number[]) => void; pending?: boolean }
  onRowClick?: (row: T) => void
  emptyTitle?: string
  emptyBody?: string
}) {
  const [selected, setSelected] = useState<Set<number>>(new Set())

  const pageIds = rows.map((r) => r.id)
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.has(id))
  const someSelected = selected.size > 0 && !allSelected

  function toggleAll() {
    setSelected(allSelected ? new Set() : new Set(pageIds))
  }

  function toggleOne(id: number) {
    const next = new Set(selected)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    setSelected(next)
  }

  function handleSort(column: string) {
    onSortChange(
      sort.column === column
        ? { column, direction: sort.direction === 'asc' ? 'desc' : 'asc' }
        : { column, direction: 'desc' },
    )
  }

  const selectable = bulkAction !== undefined

  return (
    <div className="overflow-hidden rounded-[24px] bg-surface shadow-[var(--k-sh-2)]">
      {selectable && selected.size > 0 && (
        <div className="flex items-center justify-between gap-3 border-b border-[var(--k-divider)] bg-mint px-4 py-2.5">
          <span className="numeric text-[13px] font-medium text-primary-dark">
            حُدِّد {formatNumber(selected.size)}
          </span>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setSelected(new Set())}
              className="cursor-pointer text-xs text-[var(--k-text-secondary)]"
            >
              إلغاء التحديد
            </button>

            <button
              type="button"
              disabled={bulkAction.pending}
              onClick={() => {
                bulkAction.onConfirm([...selected])
                setSelected(new Set())
              }}
              className="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-[#8a2b2b] px-3 py-1.5 text-xs font-semibold text-white hover:brightness-110 disabled:opacity-50"
            >
              <TrashIcon />
              {bulkAction.label}
            </button>
          </div>
        </div>
      )}

      <div className="overflow-x-auto">
        <table className="w-full text-right text-[13px]">
          <thead className="bg-surface-muted text-[11px] text-[var(--k-text-muted)]">
            <tr>
              {selectable && (
                <th className="w-10 p-3">
                  <input
                    type="checkbox"
                    aria-label="تحديد كل صفوف الصفحة"
                    checked={allSelected}
                    ref={(el) => {
                      if (el) el.indeterminate = someSelected
                    }}
                    onChange={toggleAll}
                    className="size-4 cursor-pointer accent-[var(--k-primary)]"
                  />
                </th>
              )}

              {columns.map((column) => (
                <th key={column.key} className={`p-3 font-medium ${alignClass(column.align)}`}>
                  {column.sortable === true ? (
                    <button
                      type="button"
                      onClick={() => handleSort(column.key)}
                      className="inline-flex cursor-pointer items-center gap-1 hover:text-ink"
                    >
                      {column.label}
                      <SortIcon active={sort.column === column.key} direction={sort.direction} />
                    </button>
                  ) : (
                    column.label
                  )}
                </th>
              ))}

              {rowActions.length > 0 && <th className="w-24 p-3 font-medium">إجراءات</th>}
            </tr>
          </thead>

          <tbody>
            {isLoading &&
              Array.from({ length: 5 }, (_, i) => (
                <tr key={i} className="border-t border-[var(--k-divider)]">
                  <td colSpan={columns.length + (selectable ? 1 : 0) + (rowActions.length > 0 ? 1 : 0)}>
                    <div
                      className="m-2 h-8 rounded-lg"
                      style={{
                        background:
                          'linear-gradient(90deg, var(--k-surface) 25%, var(--k-surface-muted) 50%, var(--k-surface) 75%)',
                        backgroundSize: '200% 100%',
                        animation: 'kf-shimmer 1.4s linear infinite',
                      }}
                    />
                  </td>
                </tr>
              ))}

            {!isLoading &&
              rows.map((row) => (
                <tr
                  key={row.id}
                  className={`border-t border-[var(--k-divider)] transition-colors hover:bg-surface-alt ${
                    onRowClick !== undefined ? 'cursor-pointer' : ''
                  }`}
                  onClick={() => onRowClick?.(row)}
                >
                  {selectable && (
                    <td className="p-3" onClick={(e) => e.stopPropagation()}>
                      <input
                        type="checkbox"
                        aria-label={`تحديد الصفّ ${row.id}`}
                        checked={selected.has(row.id)}
                        onChange={() => toggleOne(row.id)}
                        className="size-4 cursor-pointer accent-[var(--k-primary)]"
                      />
                    </td>
                  )}

                  {columns.map((column) => (
                    <td key={column.key} className={`p-3 ${alignClass(column.align)}`}>
                      {column.render(row)}
                    </td>
                  ))}

                  {rowActions.length > 0 && (
                    <td className="p-3" onClick={(e) => e.stopPropagation()}>
                      <div className="flex items-center gap-1">
                        {rowActions
                          .filter((action) => action.hidden?.(row) !== true)
                          .map((action) => (
                            <button
                              key={action.label}
                              type="button"
                              title={action.label}
                              aria-label={action.label}
                              onClick={() => action.onClick(row)}
                              className={`grid size-8 cursor-pointer place-items-center rounded-lg transition-colors ${
                                action.danger === true
                                  ? 'text-[#8a2b2b] hover:bg-[rgb(200_60_60/0.1)]'
                                  : 'text-[var(--k-text-secondary)] hover:bg-mint hover:text-primary'
                              }`}
                            >
                              {action.icon}
                            </button>
                          ))}
                      </div>
                    </td>
                  )}
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      {!isLoading && rows.length === 0 && (
        <div className="grid place-items-center p-12 text-center">
          <p className="font-heading text-base text-ink">{emptyTitle}</p>
          <p className="mt-1 text-sm text-[var(--k-text-secondary)]">{emptyBody}</p>
        </div>
      )}

      {meta !== undefined && meta.last_page > 1 && (
        <Pagination meta={meta} onPageChange={onPageChange} />
      )}
    </div>
  )
}

function Pagination({ meta, onPageChange }: { meta: PageMeta; onPageChange: (page: number) => void }) {
  const { current_page: page, last_page: last, total } = meta

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--k-divider)] px-4 py-3">
      <span className="numeric text-[11px] text-[var(--k-text-muted)]">
        صفحة {formatNumber(page)} من {formatNumber(last)} · {formatNumber(total)} نتيجة
      </span>

      <div className="flex items-center gap-1">
        <PageButton disabled={page === 1} onClick={() => onPageChange(page - 1)}>
          السابق
        </PageButton>

        {pageWindow(page, last).map((p) => (
          <button
            key={p}
            type="button"
            onClick={() => onPageChange(p)}
            className={`numeric size-8 cursor-pointer rounded-lg text-xs font-medium ${
              p === page ? 'bg-primary text-white' : 'text-[var(--k-text-secondary)] hover:bg-surface-muted'
            }`}
          >
            {formatNumber(p)}
          </button>
        ))}

        <PageButton disabled={page === last} onClick={() => onPageChange(page + 1)}>
          التالي
        </PageButton>
      </div>
    </div>
  )
}

function PageButton({
  disabled,
  onClick,
  children,
}: {
  disabled: boolean
  onClick: () => void
  children: ReactNode
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className="cursor-pointer rounded-lg px-2.5 py-1.5 text-xs font-medium text-[var(--k-text-secondary)] hover:bg-surface-muted disabled:cursor-not-allowed disabled:opacity-30"
    >
      {children}
    </button>
  )
}

/** نافذة من خمس صفحات حول الحالية — لا نرسم ألف زرّ. */
function pageWindow(current: number, last: number): number[] {
  const start = Math.max(1, Math.min(current - 2, last - 4))
  const end = Math.min(last, start + 4)

  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
}

function alignClass(align?: 'start' | 'center' | 'end'): string {
  if (align === 'center') return 'text-center'
  if (align === 'end') return 'text-left'
  return 'text-right'
}

function SortIcon({ active, direction }: { active: boolean; direction: 'asc' | 'desc' }) {
  return (
    <svg
      width="11"
      height="11"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2.5"
      className={active ? 'text-primary' : 'opacity-30'}
      aria-hidden
    >
      {active && direction === 'asc' ? <path d="m6 15 6-6 6 6" /> : <path d="m6 9 6 6 6-6" />}
    </svg>
  )
}

export function TrashIcon() {
  return (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m2 0v14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V6" />
      <path d="M10 11v6M14 11v6" />
    </svg>
  )
}

export function EyeIcon() {
  return (
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
      <circle cx="12" cy="12" r="3" />
    </svg>
  )
}

export function CheckCircleIcon() {
  return (
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <circle cx="12" cy="12" r="9" />
      <path d="m8.5 12 2.5 2.5L16 9.5" />
    </svg>
  )
}

export function XCircleIcon() {
  return (
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <circle cx="12" cy="12" r="9" />
      <path d="m9 9 6 6M15 9l-6 6" />
    </svg>
  )
}
