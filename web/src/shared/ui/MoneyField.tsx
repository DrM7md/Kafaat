import { formatNumber } from '@/shared/lib/format'

/**
 * حقل مبلغ: يعرض `22,000` لا `22000`، ويُظهر عملة الدولة داخل الحقل.
 *
 * `type="text"` لا `type="number"`: حقل الأرقام في المتصفّح يرفض الفاصلة،
 * فتختفي بمجرّد الكتابة. ندير التنسيق بأنفسنا، ونمنع غير الأرقام بالتصفية
 * لا بنوع الحقل — مع `inputMode="numeric"` لتظهر لوحة الأرقام على الجوّال.
 *
 * العملة تصل من `lookups.country.meta` — لا «ر.ق» مكتوبة هنا.
 */
export function MoneyField({
  label,
  value,
  currency,
  onChange,
  hint,
}: {
  label: string
  value: number | null
  /** رمز العملة كما يعرضه التصميم: «ر.ق». من قاعدة البيانات لا من الكود. */
  currency: string
  onChange: (value: number | null) => void
  hint?: string
}) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-medium text-ink">{label}</span>

      <div className="flex items-center rounded-xl border border-[var(--k-border-strong)] bg-surface focus-within:border-primary">
        <input
          type="text"
          inputMode="numeric"
          value={value === null ? '' : formatNumber(value)}
          onChange={(e) => {
            // نُسقط كل ما ليس رقماً — الفواصل التي رسمناها نحن، ولصقُ
            // «22,000 ر.ق» من مكانٍ آخر، وأي محرفٍ عابث.
            const digits = e.target.value.replace(/\D/g, '')

            onChange(digits === '' ? null : Number(digits))
          }}
          placeholder="0"
          className="numeric min-w-0 flex-1 bg-transparent px-3.5 py-2.5 text-sm outline-none"
        />

        <span className="shrink-0 border-r border-[var(--k-divider)] px-3 py-2.5 text-[12px] text-[var(--k-text-muted)]">
          {currency}
        </span>
      </div>

      {hint !== undefined && <span className="text-[11px] text-[var(--k-text-muted)]">{hint}</span>}
    </label>
  )
}
