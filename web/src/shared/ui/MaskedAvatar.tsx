/**
 * الصورة الرمزية.
 *
 * ⚠️ فارق جوهري عن نموذج التصميم:
 *    النموذج يعرض الحروف الأولى ويشوّشها بـ `blur(5px)`.
 *    هنا لا حروفَ أصلاً قبل الكشف — الخادم لا يرسلها (ADR-003).
 *    فنعرض قفلاً على تدرّج، لا نصّاً مشوّشاً.
 */
export function MaskedAvatar({
  initials,
  size = 54,
}: {
  initials?: string
  size?: number
}) {
  const locked = initials === undefined

  return (
    <div
      className="relative flex-none overflow-hidden rounded-[14px]"
      style={{
        width: size,
        height: size,
        background: 'linear-gradient(135deg, var(--k-primary), var(--k-primary-dark))',
      }}
    >
      {locked ? (
        <div
          className="absolute inset-0 grid place-items-center"
          style={{ background: 'rgb(10 58 52 / 0.35)', backdropFilter: 'blur(2px)' }}
          aria-label="هوية محجوبة"
        >
          <LockIcon size={Math.round(size / 3)} color="#fff" />
        </div>
      ) : (
        <div
          className="absolute inset-0 grid place-items-center font-numeric font-bold text-white"
          style={{ fontSize: size / 2.8, animation: 'kf-rise .5s ease both' }}
        >
          {initials}
        </div>
      )}
    </div>
  )
}

export function LockIcon({ size = 14, color = 'currentColor' }: { size?: number; color?: string }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} aria-hidden>
      <rect x="4" y="10" width="16" height="10" rx="2" />
      <path d="M8 10V7a4 4 0 0 1 8 0v3" />
    </svg>
  )
}

export function CheckIcon({ size = 14 }: { size?: number }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={2.4}
      style={{ animation: 'kf-pop .4s ease both' }}
      aria-hidden
    >
      <path d="M20 6 9 17l-5-5" />
    </svg>
  )
}
