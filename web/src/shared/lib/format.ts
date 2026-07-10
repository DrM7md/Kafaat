/**
 * الأرقام تُعرض بالأرقام اللاتينية، كما في التصميم:
 * `8 سنوات` · `22,000 – 28,000 ر.ق`
 *
 * اللاحقة `-u-nu-latn` تُبقي صياغة العربية وتُبدّل نظام الترقيم وحده.
 */
const latinNumbers = new Intl.NumberFormat('ar-EG-u-nu-latn', { useGrouping: true })

export function formatNumber(value: number): string {
  return latinNumbers.format(value)
}

export function formatQar(value: number, withCurrency = true): string {
  return withCurrency ? `${latinNumbers.format(value)} ر.ق` : latinNumbers.format(value)
}

export function formatSalary(range: { min: number | null; max: number | null } | null): string {
  if (range === null || (range.min === null && range.max === null)) {
    return 'غير محدّد'
  }

  if (range.min !== null && range.max !== null) {
    return `${latinNumbers.format(range.min)} – ${latinNumbers.format(range.max)} ر.ق`
  }

  return formatQar(range.min ?? range.max ?? 0)
}

export function formatYears(years: number): string {
  if (years === 0) return 'أقلّ من سنة'
  if (years === 1) return 'سنة واحدة'
  if (years === 2) return 'سنتان'
  if (years <= 10) return `${latinNumbers.format(years)} سنوات`
  return `${latinNumbers.format(years)} سنة`
}

/** المعرّف العامّ كما يظهر في التصميم: «كفاءة #4821». */
export function formatRef(ref: string): string {
  return `كفاءة #${ref}`
}
