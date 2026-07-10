/**
 * هياكل التحميل.
 *
 * ★ الفرق بين «سريع» و«يبدو سريعاً»: دوّارٌ في منتصف شاشةٍ بيضاء يُشعر
 *   بالانتظار، وهيكلٌ بمقاسات المحتوى النهائي يُشعر بأن الصفحة وصلت وأن
 *   التفاصيل تترشّح — بلا قفزةٍ في التخطيط حين تصل البيانات.
 *
 * نفس نمط الوميض المستعمل في `BrowsePage` و`DataTable`.
 */
export function Shimmer({ className = '' }: { className?: string }) {
  return (
    <div
      className={`rounded-lg ${className}`}
      style={{
        background:
          'linear-gradient(90deg, var(--k-surface) 25%, var(--k-surface-muted) 50%, var(--k-surface) 75%)',
        backgroundSize: '200% 100%',
        animation: 'kf-shimmer 1.4s linear infinite',
      }}
      aria-hidden
    />
  )
}

/** هيكل صفحة تفاصيل المرشّح — بمقاسات البطاقة الحقيقية. */
export function CandidateDetailSkeleton() {
  return (
    <article className="mx-auto max-w-3xl overflow-hidden rounded-[24px] border border-[var(--k-border)] bg-surface">
      <Shimmer className="h-40 rounded-none" />

      <div className="flex flex-col items-center px-7 pb-7 pt-14">
        <Shimmer className="h-7 w-44" />
        <Shimmer className="mt-2.5 h-4 w-56" />
        <Shimmer className="mt-2.5 h-3 w-28" />

        <div className="my-5 flex w-full gap-3">
          <Shimmer className="h-16 flex-1 rounded-[14px]" />
          <Shimmer className="h-16 flex-1 rounded-[14px]" />
          <Shimmer className="h-16 flex-1 rounded-[14px]" />
        </div>

        <Shimmer className="h-16 w-full" />
        <Shimmer className="mt-5 h-12 w-full rounded-[14px]" />
      </div>
    </article>
  )
}

/** بديل الصفحات المؤجّلة التحميل — يملأ مكان المحتوى بلا قفزة. */
export function PageSkeleton() {
  return (
    <div className="flex flex-col gap-4">
      <Shimmer className="h-9 w-56" />
      <Shimmer className="h-64 w-full rounded-[20px]" />
    </div>
  )
}
