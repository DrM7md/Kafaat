import { useState } from 'react'
import { BackToSettings } from './BackToSettings'
import {
  applyAppearance,
  DEFAULT_APPEARANCE,
  FONTS,
  loadAppearance,
  saveAppearance,
  SLOTS,
  type Appearance,
  type FontId,
  type FontSlot,
} from '@/shared/lib/appearance'

const FONT_IDS = Object.keys(FONTS) as FontId[]

/**
 * المظهر — اختيار خطوط العناوين والشعار والنصّ.
 *
 * التغيير يُطبَّق فوراً على `:root` (فتتبدّل المنصّة كلّها تحت عينك، وهذه
 * الصفحة منها) ويُحفظ في المتصفّح. لا زرّ «حفظ»: الاختيار هو الحفظ.
 */
export function AppearancePage() {
  const [appearance, setAppearance] = useState<Appearance>(loadAppearance)

  function apply(next: Appearance) {
    setAppearance(next)
    applyAppearance(next)
    saveAppearance(next)
  }

  const isDefault = (Object.keys(DEFAULT_APPEARANCE) as FontSlot[]).every(
    (slot) => appearance[slot] === DEFAULT_APPEARANCE[slot],
  )

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6">
      <BackToSettings />

      <header className="flex items-start justify-between gap-3">
        <div>
          <h1 className="font-heading text-2xl text-ink">المظهر</h1>
          <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
            اختر خطوط العناوين والشعار والنصّ. يُحفظ في متصفّحك ويُطبَّق على المنصّة فوراً.
          </p>
        </div>

        <button
          type="button"
          onClick={() => apply(DEFAULT_APPEARANCE)}
          disabled={isDefault}
          className="shrink-0 cursor-pointer rounded-full bg-surface px-3.5 py-2 text-xs font-medium shadow-[var(--k-sh-1)] transition-[transform,box-shadow] duration-200 hover:-translate-y-px hover:text-primary hover:shadow-[var(--k-sh-2)] disabled:cursor-default disabled:opacity-40 disabled:hover:translate-y-0"
        >
          إعادة الافتراضي
        </button>
      </header>

      <PreviewCard />

      {SLOTS.map((meta) => (
        <section
          key={meta.slot}
          className="flex flex-col gap-4 rounded-[24px] bg-surface p-6 shadow-[var(--k-sh-2)]"
        >
          <div>
            <h2 className="font-heading text-base text-ink">{meta.title}</h2>
            <p className="mt-0.5 text-[12.5px] text-[var(--k-text-secondary)]">{meta.hint}</p>
          </div>

          <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
            {FONT_IDS.map((id) => (
              <FontOption
                key={id}
                selected={appearance[meta.slot] === id}
                sample={meta.sample}
                fontId={id}
                onSelect={() => apply({ ...appearance, [meta.slot]: id })}
              />
            ))}
          </div>
        </section>
      ))}
    </div>
  )
}

/** لوحة معاينة حيّة — تقرأ خطوطها من `:root` فتتبدّل مع كل اختيار بلا كود. */
function PreviewCard() {
  return (
    <div className="overflow-hidden rounded-[24px] bg-surface shadow-[var(--k-sh-2)]">
      <div
        className="flex items-center justify-between px-6 py-4"
        style={{ background: 'linear-gradient(140deg, var(--k-mint), rgb(228 237 235 / 0.35))' }}
      >
        <span className="font-logo text-2xl font-bold text-primary">كفاءات</span>
        <span className="text-[10px] font-semibold uppercase tracking-[2px] text-[var(--k-text-muted)]">
          معاينة
        </span>
      </div>

      <div className="p-6">
        <h3 className="font-heading text-xl text-ink">موظّفك القادم خلف السِّتار</h3>
        <p className="mt-2 text-[13px] leading-7 text-[var(--k-text-secondary)]">
          كفاءاتٌ موثّقة بهويّةٍ محجوبة. حين تجد المناسب، ارفع السِّتارة واكشف بيانات التواصل بنقرة.
          الأرقام تبقى بخطّها: <span className="numeric font-bold text-ink">٢٢٬٠٠٠ – ٢٨٬٠٠٠</span> ر.ق.
        </p>
      </div>
    </div>
  )
}

function FontOption({
  selected,
  sample,
  fontId,
  onSelect,
}: {
  selected: boolean
  sample: string
  fontId: FontId
  onSelect: () => void
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      aria-pressed={selected}
      className={`flex cursor-pointer flex-col items-start gap-1.5 rounded-[16px] border-2 p-3.5 text-right transition-[transform,border-color,box-shadow] duration-200 hover:-translate-y-0.5 ${
        selected
          ? 'border-primary bg-mint/40 shadow-[var(--k-sh-1)]'
          : 'border-transparent bg-surface-alt shadow-[var(--k-sh-1)] hover:border-[var(--k-border-strong)]'
      }`}
    >
      <span className="flex w-full items-center justify-between gap-2">
        <span className="truncate text-[19px] text-ink" style={{ fontFamily: FONTS[fontId].stack }}>
          {sample}
        </span>
        {selected && <CheckMark />}
      </span>
      <span className="text-[11px] font-medium text-[var(--k-text-muted)]">{FONTS[fontId].label}</span>
    </button>
  )
}

function CheckMark() {
  return (
    <span
      className="grid size-5 shrink-0 place-items-center rounded-full bg-primary text-white"
      style={{ animation: 'kf-pop .35s var(--k-ease) both' }}
      aria-hidden
    >
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
        <path d="m5 12 5 5 9-11" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    </span>
  )
}
