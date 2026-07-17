import type { Lookup } from '@/shared/api/types'
import { StatusBadge } from '@/shared/ui/StatusBadge'
import { statusForReadiness } from '@/shared/lib/readiness'

/**
 * ★ مؤشّر الرغبة في الانتقال — شريطٌ يحرّكه الموظّف، لا قائمةٌ يختار منها.
 *
 * لماذا لا قائمة؟ لأنّ «راغب في ترك عمله» **اعترافٌ** لا معلومة: من يخشى أن
 * يبلغ مديرَه لن يختارها ولو كان يبحث منذ سنة، ومن لا يخشى سيختارها ولو كان
 * مرتاحاً — لأنها تجلب انتباه الشركات. فتمتلئ الخانةُ بلا أن تحمل خبراً.
 *
 * الشريط يسأل «كم؟» لا «هل؟». والتدرّجُ يُقال بصدقٍ لا يُقال به الاسمُ الصريح.
 *
 * ★★ والشارةُ تحته تتبدّل تحت إصبعه وهو يسحب: يرى **بالضبط** ما ستراه الشركات
 *    قبل أن يحفظ. لا مفاجأة، ولا حالةٌ نُسبت إليه دون أن يقصدها — وهذه هي
 *    الثقة التي بلا شيءٍ منها لا يصدق أحدٌ في هذا الحقل أصلاً.
 */
export function ReadinessSlider({
  value,
  statuses,
  onChange,
}: {
  value: number | null
  statuses: Lookup[]
  onChange: (value: number) => void
}) {
  // من لم يضبطه قطّ يبدأ من الصفر — «مستقرّ» هو الافتراض الآمن: لا نُعلن عن
  // أحدٍ رغبةً في الرحيل لم يُعلنها بنفسه.
  const readiness = value ?? 0
  const preview = statusForReadiness(statuses, readiness)

  return (
    <div className="flex flex-col gap-3 rounded-[16px] border border-[var(--k-border)] bg-canvas p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-[13px] font-semibold text-ink">رغبتك في الانتقال</p>
          <p className="mt-0.5 text-[11px] text-[var(--k-text-secondary)]">
            لا يظهر الرقم لأحد — تظهر الشارة وحدها.
          </p>
        </div>

        {preview !== undefined && <StatusBadge status={preview} />}
      </div>

      <input
        type="range"
        min={0}
        max={100}
        step={5}
        value={readiness}
        onChange={(e) => onChange(Number(e.target.value))}
        aria-label="رغبتك في الانتقال"
        aria-valuetext={preview?.label ?? String(readiness)}
        className="k-range cursor-pointer"
        style={{
          // التعبئة تتبع القيمة — ويُقلب اتجاهها لأنّ الصفحة RTL: بلا `to left`
          // يمتلئ الشريط من الجهة الخطأ فيبدو كأنّه يتناقص كلّما زاد.
          background: `linear-gradient(to left, var(--k-primary) ${readiness}%, var(--k-border-strong) ${readiness}%)`,
        }}
      />

      {/* المرساتان تقولان للمستخدم ما طرفا الشريط — بلا رقمٍ ولا نسبة. */}
      <div className="flex justify-between text-[11px] text-[var(--k-text-muted)]">
        <span>لا أفكّر بالانتقال</span>
        <span>أبحث بجدّية</span>
      </div>
    </div>
  )
}

/**
 * ★ «ما زلتَ كما أنت؟» — نقرةٌ واحدة تُجدّد الحالة.
 *
 * أرخصُ فعلٍ في المنصّة وأثمنُ ما فيها. بلا هذا التنبيه تشيخ كلُّ الحالات
 * بصمت: موظّفٌ أعلن رغبته في الترك قبل ثمانية أشهر ثم تُرقّي واستقرّ — تبقى
 * شارته خضراء، وتدفع الشركةُ رسم الكشف لتكتشف ذلك بنفسها. وذلك أوّلُ ما
 * يُطالَب به استردادٌ، وآخرُ ما تُغفَر به ثقة.
 */
export function ReadinessRefreshNotice({
  ageDays,
  stale,
  pending,
  onConfirm,
  onDismiss,
}: {
  ageDays: number
  stale: boolean
  pending: boolean
  onConfirm: () => void
  onDismiss: () => void
}) {
  return (
    <div
      className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3.5 text-[13px]"
      style={{
        borderColor: stale ? 'rgb(200 60 60 / 0.35)' : 'rgb(180 134 59 / 0.35)',
        background: stale ? 'rgb(200 60 60 / 0.06)' : 'var(--k-gold-tint)',
        color: stale ? '#8a2b2b' : '#96682A',
      }}
    >
      {/* ⚠️ لا تقل «ملفّك لا يظهر»: الملفّ يظهر — الشارةُ وحدها تُطفأ. الشركة
          ترى «حالة قديمة» رمادية بدل «راغب في ترك عمله». */}
      <p>
        <strong>{stale ? 'حالتك بائتة — تراها الشركات «قديمة»' : 'ما زلت كما أنت؟'}</strong> — لم
        تُحدِّث حالتك منذ <span className="numeric">{ageDays}</span> يوماً.
      </p>

      <div className="flex shrink-0 gap-2">
        <button
          type="button"
          disabled={pending}
          onClick={onConfirm}
          className="cursor-pointer rounded-lg bg-primary px-3 py-1.5 text-[12px] font-semibold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0 disabled:opacity-60"
        >
          {pending ? 'جارٍ…' : 'نعم، كما أنا'}
        </button>

        <button
          type="button"
          onClick={onDismiss}
          className="cursor-pointer rounded-lg border border-current px-3 py-1.5 text-[12px] font-medium"
        >
          سأُعدّلها
        </button>
      </div>
    </div>
  )
}
