import type { Lookup } from '@/shared/api/types'

/**
 * الحالة الوظيفية المقابلة لنسبةٍ على مؤشّر الانتقال — نسخةُ الواجهة من
 * `App\Services\Readiness\ReadinessMapper`.
 *
 * ⚠️ هذه للمعاينة الحيّة وحدها: أن يرى الموظّف الشارةَ تتبدّل تحت إصبعه وهو
 *    يسحب الشريط. **الخادم هو من يقرّر** — الواجهة لا ترسل `employment_status_id`
 *    إطلاقاً، بل النسبةَ وحدها، فيشتقّها الخادم بنفسه.
 *
 *    ولهذا لا خطرَ في ازدواج القاعدة: أسوأ ما يقع لو اختلفت النسختان أن يرى
 *    الموظّف شارةً في المعاينة ثم يجدها غيرَها بعد الحفظ — لا أن يُخزَّن في
 *    القاعدة تناقضٌ بين نسبته وحالته.
 *
 * والعتبات تُقرأ من `meta.readiness_min` لا من الكود: حالةٌ خامسة يضيفها
 * المشرف من اللوحة تعمل هنا بلا نشرِ واجهة.
 */
export function statusForReadiness(statuses: Lookup[], readiness: number): Lookup | undefined {
  const tiers = statuses
    .filter((status) => typeof status.meta?.readiness_min === 'number')
    .sort((a, b) => (b.meta?.readiness_min ?? 0) - (a.meta?.readiness_min ?? 0))

  // القائمة تنازلية، فأوّلُ ما تبلغه النسبة هو أعلى حالةٍ تستحقّها. ولو لم
  // تبلغ أدناها، فأدنى الحالات هي حالُه — لا يبقى أحدٌ بلا شارة.
  return tiers.find((status) => (status.meta?.readiness_min ?? 0) <= readiness) ?? tiers.at(-1)
}

/** «محدَّثة اليوم» · «قبل ٣ أيام» · «قبل شهرين» — بلا عدّادٍ يُرهق العين. */
export function formatStatusAge(days: number): string {
  if (days === 0) return 'محدَّثة اليوم'
  if (days === 1) return 'محدَّثة أمس'
  if (days < 14) return `محدَّثة قبل ${days} أيام`
  if (days < 60) return `محدَّثة قبل ${Math.floor(days / 7)} أسابيع`

  return `محدَّثة قبل ${Math.floor(days / 30)} أشهر`
}
