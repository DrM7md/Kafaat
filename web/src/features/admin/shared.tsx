export function AdminToolbar({
  search,
  onSearch,
  placeholder,
  filterValue,
  onFilter,
  filterOptions,
  busy = false,
}: {
  search: string
  onSearch: (value: string) => void
  placeholder: string
  filterValue?: string
  onFilter?: (value: string) => void
  filterOptions?: { value: string; label: string }[]
  busy?: boolean
}) {
  return (
    <div className="flex flex-wrap items-center gap-3">
      <label className="flex min-w-[240px] flex-1 items-center gap-2.5 rounded-xl border border-[var(--k-border-strong)] bg-surface px-3.5 py-2.5">
        <SearchIcon />
        <input
          type="search"
          value={search}
          onChange={(e) => onSearch(e.target.value)}
          placeholder={placeholder}
          className="w-full bg-transparent text-[13px] outline-none placeholder:text-[var(--k-text-muted)]"
        />
        {busy && (
          <span
            aria-label="جارٍ البحث"
            className="size-3.5 shrink-0 animate-spin rounded-full border-2 border-[var(--k-border)] border-t-primary"
          />
        )}
      </label>

      {filterOptions !== undefined && onFilter !== undefined && (
        <select
          value={filterValue}
          onChange={(e) => onFilter(e.target.value)}
          aria-label="تصفية بالحالة"
          className={`cursor-pointer rounded-full border px-3.5 py-2.5 text-xs font-medium outline-none ${
            filterValue === ''
              ? 'border-[var(--k-border-strong)] bg-surface text-[var(--k-text-secondary)]'
              : 'border-[rgb(15_80_73/0.2)] bg-mint text-primary'
          }`}
        >
          {filterOptions.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      )}
    </div>
  )
}

const VERIFICATION: Record<string, { label: string; bg: string; color: string }> = {
  verified: { label: 'موثّقة', bg: '#E7F3EA', color: '#1E7A44' },
  pending: { label: 'قيد التوثيق', bg: '#FBF0D9', color: '#96682A' },
  unverified: { label: 'غير موثّقة', bg: '#ECEFEE', color: '#566460' },
  rejected: { label: 'مرفوضة', bg: 'rgb(200 60 60 / 0.1)', color: '#8a2b2b' },
}

export function VerificationBadge({ status }: { status: string }) {
  const meta = VERIFICATION[status] ?? VERIFICATION.unverified

  return <Pill bg={meta.bg} color={meta.color} label={meta.label} />
}

const APPROVAL: Record<string, { label: string; bg: string; color: string }> = {
  draft: { label: 'مسودّة', bg: '#ECEFEE', color: '#566460' },
  pending: { label: 'بانتظار الاعتماد', bg: '#FBF0D9', color: '#96682A' },
  approved: { label: 'معتمَد', bg: '#E7F3EA', color: '#1E7A44' },
  rejected: { label: 'مرفوض', bg: 'rgb(200 60 60 / 0.1)', color: '#8a2b2b' },
}

export function ApprovalBadge({ status }: { status: string }) {
  const meta = APPROVAL[status] ?? APPROVAL.draft

  return <Pill bg={meta.bg} color={meta.color} label={meta.label} />
}

const REVEAL: Record<string, { label: string; bg: string; color: string }> = {
  pending: { label: 'بانتظار الدفع', bg: '#FBF0D9', color: '#96682A' },
  completed: { label: 'مكتمل', bg: '#E7F3EA', color: '#1E7A44' },
  failed: { label: 'فاشل', bg: '#ECEFEE', color: '#566460' },
  refunded: { label: 'مُسترَدّ', bg: '#ECEFEE', color: '#566460' },
}

export function RevealBadge({ status }: { status: string }) {
  const meta = REVEAL[status] ?? REVEAL.failed

  return <Pill bg={meta.bg} color={meta.color} label={meta.label} />
}

function Pill({ bg, color, label }: { bg: string; color: string; label: string }) {
  return (
    <span
      className="inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-medium"
      style={{ background: bg, color }}
    >
      {label}
    </span>
  )
}

export function CompletenessBar({ value }: { value: number }) {
  return (
    <div className="flex items-center gap-2">
      <div className="h-1.5 w-16 overflow-hidden rounded-full bg-surface-muted">
        <div
          className="h-full rounded-full"
          style={{ width: `${value}%`, background: value >= 60 ? 'var(--k-primary)' : 'var(--k-gold)' }}
        />
      </div>
      <span className="numeric text-[11px] text-[var(--k-text-muted)]">{value}٪</span>
    </div>
  )
}

function SearchIcon() {
  return (
    <svg
      width="16"
      height="16"
      viewBox="0 0 24 24"
      fill="none"
      stroke="rgb(22 33 31 / 0.4)"
      strokeWidth="2"
      className="shrink-0"
      aria-hidden
    >
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    </svg>
  )
}
