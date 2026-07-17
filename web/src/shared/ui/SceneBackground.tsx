import { useReducedMotion } from '@/shared/hooks/useReducedMotion'

/**
 * خلفية المشهد — طبقةٌ ثابتة خلف كل المحتوى.
 *
 * طبقتان:
 *   ① شبكةٌ تخطيطية بترولية دقيقة (خطوطٌ صغرى كل 32px، وكبرى كل 160px) تنجرف
 *      قطرياً ببطء. تخفت عند الأطراف بقناعٍ شعاعيّ فلا تبدو ورقَ جدار — بل
 *      مخطّطاً هادئاً أقوى قربَ الأعلى حيث الافتتاحية.
 *   ② حبيبات ورقٍ رقيقة تُدفئ السطح وتزيل الإحساس «البلاستيكي».
 *
 * كلاهما زخرفيّ بحت: `aria-hidden` و`pointer-events-none`، وتتجمّد الحركة عند
 * تفضيل الحركة المخفّضة (الشبكة تبقى، الانجرافُ يذهب).
 */
export function SceneBackground() {
  const reduced = useReducedMotion()

  const mask = 'radial-gradient(130% 100% at 50% 0%, #000 42%, transparent 92%)'

  return (
    <div aria-hidden className="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
      <div
        className="absolute inset-0"
        style={{
          backgroundImage: [
            // خطوط صغرى (شبكة 32px)
            'repeating-linear-gradient(0deg, rgb(15 80 73 / 0.05) 0 1px, transparent 1px 32px)',
            'repeating-linear-gradient(90deg, rgb(15 80 73 / 0.05) 0 1px, transparent 1px 32px)',
            // خطوط كبرى (كل 5 خلايا = 160px) أوضح قليلاً — إيقاعُ المخطّط
            'repeating-linear-gradient(0deg, rgb(15 80 73 / 0.06) 0 1.5px, transparent 1.5px 160px)',
            'repeating-linear-gradient(90deg, rgb(15 80 73 / 0.06) 0 1.5px, transparent 1.5px 160px)',
          ].join(','),
          WebkitMaskImage: mask,
          maskImage: mask,
          animation: reduced ? undefined : 'kf-grid-pan 30s linear infinite',
          willChange: reduced ? undefined : 'background-position',
        }}
      />

      {/* حبيبات الورق — تُضرب في اللون (multiply) فتخفت لا تُبيّض */}
      <div
        className="absolute inset-0"
        style={{
          opacity: 0.45,
          mixBlendMode: 'multiply',
          backgroundImage:
            "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.045'/%3E%3C/svg%3E\")",
        }}
      />
    </div>
  )
}
