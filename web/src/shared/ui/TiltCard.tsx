import { useRef, type ComponentPropsWithoutRef, type MouseEvent } from 'react'
import { useReducedMotion } from '@/shared/hooks/useReducedMotion'

const MAX_TILT = 7 // أقصى ميلٍ بالدرجات — لطيفٌ لا يُدوّخ

/**
 * بطاقةٌ تُدفَع إلى الداخل بالماوس.
 *
 * حين يمرّ المؤشّر، تميل البطاقة بمنظورٍ ثلاثي الأبعاد بحيث **تنحسر النقطةُ
 * تحت المؤشّر إلى الداخل** — إحساسُ أنّك تضغط لوحةً حقيقية، لا أنّها ترتفع نحوك.
 * تتبعها لمعةٌ بيضاء ناعمة (soft-light) تُبرز مكان الإصبع.
 *
 * الأداء: كل تحديثٍ يمرّ عبر `requestAnimationFrame` واحد (لا نُثقل بكل حدث
 * حركة). والحركة تُطفأ كلّياً عند تفضيل الحركة المخفّضة — تبقى البطاقة مسطّحة.
 *
 * يُمرّر بقيّة الخصائص (onMouseEnter/onFocusCapture/onTouchStart للجلب المسبق)
 * كما هي إلى `<article>`، ويدمج معالِجَيه دون أن يبتلع أيّ معالجٍ خارجي.
 */
export function TiltCard({
  children,
  className,
  style,
  onMouseMove,
  onMouseLeave,
  ...rest
}: ComponentPropsWithoutRef<'article'>) {
  const ref = useRef<HTMLElement>(null)
  const glareRef = useRef<HTMLDivElement>(null)
  const frame = useRef(0)
  const reduced = useReducedMotion()

  function handleMove(e: MouseEvent<HTMLElement>) {
    onMouseMove?.(e)
    if (reduced) return

    const el = ref.current
    if (el === null) return

    const rect = el.getBoundingClientRect()
    const px = (e.clientX - rect.left) / rect.width // 0..1 يمين↔يسار
    const py = (e.clientY - rect.top) / rect.height // 0..1 أعلى↔أسفل

    cancelAnimationFrame(frame.current)
    frame.current = requestAnimationFrame(() => {
      // «دفعٌ إلى الداخل»: النقطة تحت المؤشّر تنحسر بعيداً عنك، لا ترتفع نحوك.
      //   rotateX موجب ⇒ الحافة العليا تغوص ⇒ نستعمله حين يكون المؤشّر أعلى.
      //   rotateY موجب ⇒ الحافة اليمنى تغوص ⇒ نستعمله حين يكون المؤشّر يميناً.
      const rx = (0.5 - py) * 2 * MAX_TILT
      const ry = (px - 0.5) * 2 * MAX_TILT

      el.style.transition = 'transform .1s var(--k-ease), box-shadow .3s var(--k-ease)'
      el.style.transform = `perspective(1100px) rotateX(${rx}deg) rotateY(${ry}deg) translateY(-4px) scale(1.008)`

      const glare = glareRef.current
      if (glare !== null) {
        glare.style.opacity = '1'
        glare.style.background = `radial-gradient(220px circle at ${px * 100}% ${py * 100}%, rgb(255 255 255 / 0.42), transparent 62%)`
      }
    })
  }

  function handleLeave(e: MouseEvent<HTMLElement>) {
    onMouseLeave?.(e)
    cancelAnimationFrame(frame.current)

    const el = ref.current
    if (el !== null) {
      // عودةٌ أبطأ وألين من التتبّع — استرخاءٌ ناعم إلى السكون.
      el.style.transition = 'transform .5s var(--k-ease), box-shadow .3s var(--k-ease)'
      el.style.transform = ''
    }
    const glare = glareRef.current
    if (glare !== null) glare.style.opacity = '0'
  }

  return (
    <article
      ref={ref}
      onMouseMove={handleMove}
      onMouseLeave={handleLeave}
      className={className}
      style={style}
      {...rest}
    >
      {children}
      <div
        ref={glareRef}
        aria-hidden
        className="pointer-events-none absolute inset-0 rounded-[inherit] opacity-0 transition-opacity duration-300"
        style={{ mixBlendMode: 'soft-light' }}
      />
    </article>
  )
}
