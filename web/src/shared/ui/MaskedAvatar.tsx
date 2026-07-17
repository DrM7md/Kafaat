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
      className="relative flex-none overflow-hidden rounded-[16px]"
      style={{
        width: size,
        height: size,
        background: 'linear-gradient(140deg, var(--k-primary-lite), var(--k-primary-abyss))',
        boxShadow: 'inset 0 1px 0 rgb(255 255 255 / 0.12)',
      }}
    >
      {locked ? (
        <div
          className="absolute inset-0 grid place-items-center"
          style={{ background: 'rgb(6 35 31 / 0.42)', backdropFilter: 'blur(2px)' }}
          aria-label="هوية محجوبة"
        >
          {/* السِّتار — لمعةٌ تمرّ على الزجاج المحبَّب: إيحاءٌ بإنسانٍ خلفه */}
          <span
            aria-hidden
            className="pointer-events-none absolute inset-0"
            style={{
              background:
                'linear-gradient(115deg, transparent 30%, rgb(255 255 255 / 0.22) 50%, transparent 70%)',
              backgroundSize: '250% 100%',
              animation: 'kf-sheen 3.4s var(--k-ease) infinite',
            }}
          />
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
