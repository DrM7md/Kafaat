import { Link } from 'react-router'

/**
 * رجوعٌ إلى لوحة الإعدادات.
 *
 * لازمٌ لا زينة: هذه الصفحات غادرت شريط التنقّل، فلم يبقَ لها بابٌ إلّا
 * اللوحة. صفحةٌ عميقةٌ بلا طريق رجوعٍ ظاهر تدفع المستخدم إلى زرّ المتصفّح —
 * أو إلى الضياع.
 */
export function BackToSettings() {
  return (
    <Link
      to="/admin/settings"
      className="mb-2 inline-flex items-center gap-1.5 text-[12.5px] font-medium text-[var(--k-text-secondary)] hover:text-primary"
    >
      <span aria-hidden>→</span>
      الإعدادات
    </Link>
  )
}
