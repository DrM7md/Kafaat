/**
 * الأرقام تُعرض بالصيغة العربية الهندية، كما في التصميم:
 * `٨ سنوات` · `٢٢٬٠٠٠ – ٢٨٬٠٠٠ ر.ق`
 */
const arabicNumbers = new Intl.NumberFormat('ar-EG', { useGrouping: true })

export function formatNumber(value: number): string {
  return arabicNumbers.format(value)
}

export function formatQar(value: number, withCurrency = true): string {
  return withCurrency ? `${arabicNumbers.format(value)} ر.ق` : arabicNumbers.format(value)
}

export function formatSalary(range: { min: number | null; max: number | null } | null): string {
  if (range === null || (range.min === null && range.max === null)) {
    return 'غير محدّد'
  }

  if (range.min !== null && range.max !== null) {
    return `${arabicNumbers.format(range.min)} – ${arabicNumbers.format(range.max)} ر.ق`
  }

  return formatQar(range.min ?? range.max ?? 0)
}

export function formatYears(years: number): string {
  if (years === 0) return 'أقلّ من سنة'
  if (years === 1) return 'سنة واحدة'
  if (years === 2) return 'سنتان'
  if (years <= 10) return `${arabicNumbers.format(years)} سنوات`
  return `${arabicNumbers.format(years)} سنة`
}

/** المعرّف العامّ كما يظهر في التصميم: «كفاءة #4821». */
export function formatRef(ref: string): string {
  return `كفاءة #${ref}`
}
