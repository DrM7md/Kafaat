import type { ReactNode } from 'react'

export function AuthShell({
  title,
  subtitle,
  wide = false,
  children,
}: {
  title: string
  subtitle: string
  /** بطاقتان متجاورتان لا نموذجٌ واحد — تحتاجان ضعف العرض. */
  wide?: boolean
  children: ReactNode
}) {
  return (
    <main className="grid min-h-screen place-items-center p-6">
      <div
        className={`w-full rounded-[28px] bg-surface p-8 shadow-[var(--k-sh-3)] ${
          wide ? 'max-w-2xl' : 'max-w-md'
        }`}
      >
        <div className="mb-6 flex flex-col items-center gap-1">
          <span className="font-logo text-3xl font-bold text-primary">كفاءات</span>
          <span className="text-[10px] tracking-[1px] text-[var(--k-text-muted)]">KAFAAT</span>
        </div>

        <h1 className="text-center font-heading text-xl text-ink">{title}</h1>
        <p className="mb-6 mt-1 text-center text-sm text-[var(--k-text-secondary)]">{subtitle}</p>

        {children}
      </div>
    </main>
  )
}

export function Field({
  label,
  type = 'text',
  value,
  onChange,
  required = false,
  autoComplete,
  hint,
}: {
  label: string
  type?: string
  value: string
  onChange: (v: string) => void
  required?: boolean
  autoComplete?: string
  hint?: string
}) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-medium text-ink">{label}</span>
      <input
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        required={required}
        autoComplete={autoComplete}
        className="rounded-xl border border-[var(--k-border-strong)] bg-surface px-3.5 py-2.5 text-sm outline-none focus:border-primary"
      />
      {hint !== undefined && <span className="text-[11px] text-[var(--k-text-muted)]">{hint}</span>}
    </label>
  )
}

export function Checkbox({
  checked,
  onChange,
  children,
}: {
  checked: boolean
  onChange: (v: boolean) => void
  children: ReactNode
}) {
  return (
    <label className="flex cursor-pointer items-start gap-2.5 text-[12.5px] text-[var(--k-text-secondary)]">
      <input
        type="checkbox"
        checked={checked}
        onChange={(e) => onChange(e.target.checked)}
        className="mt-0.5 size-4 accent-[var(--k-primary)]"
      />
      <span>{children}</span>
    </label>
  )
}

export function SubmitButton({ pending, children }: { pending: boolean; children: ReactNode }) {
  return (
    <button
      type="submit"
      disabled={pending}
      className="mt-2 cursor-pointer rounded-[14px] bg-primary px-4 py-3 text-sm font-bold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0 disabled:cursor-wait disabled:opacity-70"
    >
      {pending ? 'جارٍ…' : children}
    </button>
  )
}
