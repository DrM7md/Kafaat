import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router'
import { CandidateCard } from '@/shared/ui/CandidateCard'
import { useReveal } from '@/features/candidates/useReveal'
import { useBillingUnderDevelopment } from '@/shared/hooks/usePublicSettings'
import { apiErrorMessage } from '@/shared/api/client'
import { jobInterestedQuery } from './queries'

/**
 * المهتمّون بوظيفة — **بطاقاتٌ محجوبة**.
 *
 * ★★ نُعيد استعمال `CandidateCard` نفسها التي تخدم التصفّح. فكل ما بُني للحجب
 *    يسري هنا تلقائياً: الاسم والهاتف **غائبان من JSON** حتى يُدفع رسم الكشف،
 *    وزرُّ الكشف يعمل بالسعر نفسه.
 *
 *    ولو بنينا لهذه الصفحة بطاقةً خاصّة لتباعدت عن قواعد الحجب مع أوّل تعديل —
 *    وصار الإعجابُ باباً خلفياً يُفرَّغ منه نموذج الربح.
 *
 *    وما تشتريه الشركة هنا أثمنُ من التصفّح: هذا المرشّح **يريد وظيفتها**.
 */
export function JobInterestedPage() {
  const { ref = '' } = useParams()
  const reveal = useReveal()
  const billingUnderDevelopment = useBillingUnderDevelopment()

  const { data, isPending } = useQuery(jobInterestedQuery(ref))

  const candidates = data?.data ?? []

  return (
    <div className="mx-auto flex max-w-4xl flex-col gap-5">
      <Link
        to="/my-jobs"
        className="inline-flex w-fit items-center gap-1.5 text-[13px] font-medium text-[var(--k-text-secondary)] hover:text-primary"
      >
        <span aria-hidden>→</span>
        العودة إلى وظائفي
      </Link>

      <header>
        <h1 className="font-heading text-2xl text-ink">المهتمّون بـ «{data?.job.title ?? '…'}»</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          هؤلاء <strong>يريدون وظيفتك</strong>. هويّاتهم محجوبة — اكشف من يناسبك للتواصل معه.
        </p>
      </header>

      {billingUnderDevelopment && (
        <p className="rounded-xl border border-[var(--k-border)] bg-surface-muted p-3 text-sm text-[var(--k-text-secondary)]">
          الدفع قيد التطوير — سيُفعَّل عند ربط بوّابة الدفع.
        </p>
      )}

      {reveal.isError && (
        <p className="rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
          {apiErrorMessage(reveal.error)}
        </p>
      )}

      {isPending ? (
        <p className="py-16 text-center text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>
      ) : candidates.length === 0 ? (
        <div className="grid place-items-center rounded-[24px] border border-dashed border-[var(--k-border-strong)] p-14 text-center">
          <p className="font-heading text-base text-ink">لا مهتمّ بعد</p>
          <p className="mt-1 text-[13px] text-[var(--k-text-secondary)]">
            سنُشعرك فور أن يُبدي مرشّحٌ اهتمامه.
          </p>
        </div>
      ) : (
        <ul className="grid gap-4 sm:grid-cols-2">
          {candidates.map((candidate) => (
            <li key={candidate.ref}>
              <CandidateCard
                candidate={candidate}
                pending={reveal.isPending}
                onReveal={(candidateRef) => reveal.mutate(candidateRef)}
              />
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
