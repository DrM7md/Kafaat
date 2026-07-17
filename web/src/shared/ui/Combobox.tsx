import { useEffect, useId, useMemo, useRef, useState } from 'react'
import type { Lookup } from '@/shared/api/types'

/** القيمة المختارة: إمّا خيارٌ من القائمة، وإمّا نصٌّ حرّ من «أخرى». */
export type ComboValue = {
  id: number | null
  /** نصّ «أخرى». حين يمتلئ، يتجاهل الخادمُ `id` ويُنشئ اقتراحاً معلَّقاً. */
  other: string
}

export const EMPTY_COMBO: ComboValue = { id: null, other: '' }

/** خيار «أخرى» ليس صفّاً في القاعدة، فيلزمه معرّفٌ لا يصطدم بمعرّفٍ حقيقي. */
const OTHER = -1

/**
 * قائمة منسدلة قابلة للبحث، بخيار «أخرى» يفتح حقل نصّ.
 *
 * البحث محلّي لا خادميّ: القوائم المرجعية عشراتُ عناصر لا آلاف، وجلبُها مرّةً
 * واحدة (`staleTime: Infinity`) أسرع من طلبٍ لكل حرف — ويعمل بلا اتصال.
 *
 * ما يكتبه المستخدم في «أخرى» لا يصير خياراً للجميع: الخادم يحفظه اقتراحاً
 * معلَّقاً ينتظر مشرفاً. راجع Brain/05-settings-engine.md §4.
 */
export function Combobox({
  label,
  value,
  options,
  onChange,
  placeholder = 'ابحث أو اختر…',
  otherLabel = 'أخرى — أضِف قيمة جديدة',
  otherHint = 'ستظهر لك فوراً، وتصير خياراً للجميع بعد اعتماد المشرف.',
  disabled = false,
  hint,
}: {
  label: string
  value: ComboValue
  options: Lookup[]
  onChange: (value: ComboValue) => void
  placeholder?: string
  otherLabel?: string
  otherHint?: string
  disabled?: boolean
  hint?: string
}) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [active, setActive] = useState(0)

  const rootRef = useRef<HTMLDivElement>(null)
  const listId = useId()

  const selected = options.find((o) => o.id === value.id) ?? null
  const isOther = value.other !== ''

  const matches = useMemo(() => {
    const q = normalize(query)
    const found = q === '' ? options : options.filter((o) => normalize(o.label).includes(q))

    // «أخرى» آخر القائمة دائماً: خيارٌ أخير لمن لم يجد نفسه فيها، لا أوّل
    // ما تقع عليه العين — وإلّا اختاره الكسول وتضخّم طابور الاقتراحات.
    return [...found, { id: OTHER, key: '__other__', label: otherLabel }]
  }, [options, query, otherLabel])

  // إغلاق بالنقر خارج المكوّن — بلا هذا تبقى القائمة مفتوحة فوق بقيّة النموذج.
  useEffect(() => {
    if (!open) return

    function onPointerDown(event: PointerEvent) {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false)
    }

    document.addEventListener('pointerdown', onPointerDown)
    return () => document.removeEventListener('pointerdown', onPointerDown)
  }, [open])

  function choose(option: Lookup | { id: number; key: string; label: string }) {
    if (option.id === OTHER) {
      // نمسح `id`: القيمة صارت نصّاً حرّاً، وإبقاء الاختيار القديم يجعل
      // الخادم يتلقّى معرّفاً ونصّاً معاً فلا يدري أيّهما قصد المستخدم.
      onChange({ id: null, other: query.trim() })
    } else {
      onChange({ id: option.id, other: '' })
    }

    setQuery('')
    setOpen(false)
  }

  function onKeyDown(event: React.KeyboardEvent) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault()
      if (!open) return setOpen(true)

      const step = event.key === 'ArrowDown' ? 1 : -1
      setActive((i) => (i + step + matches.length) % matches.length)
      return
    }

    if (event.key === 'Enter' && open) {
      event.preventDefault()
      const option = matches[active]
      if (option !== undefined) choose(option)
      return
    }

    if (event.key === 'Escape') setOpen(false)
  }

  // نصّ «أخرى» مكتوبٌ أصلاً: نعرض حقل تحريره لا القائمة.
  //
  // ربطٌ صريح بـ `htmlFor` لا تغليفٌ بـ `<label>`: التغليف كان يضمّ الزرّ
  // والتلميح، فيصير الاسمُ المقروءُ للحقل «المدينة اختر من القائمة ستظهر
  // لك فوراً…» — يسمعه قارئ الشاشة كاملاً عند كل تركيز.
  if (isOther) {
    return (
      <div className="flex flex-col gap-1.5">
        <label className="text-[13px] font-medium text-ink" htmlFor={listId}>
          {label}
        </label>

        <div className="flex gap-2">
          <input
            id={listId}
            value={value.other}
            onChange={(e) => onChange({ id: null, other: e.target.value })}
            disabled={disabled}
            autoFocus
            className="min-w-0 flex-1 rounded-xl border border-[var(--k-border-strong)] bg-surface px-3.5 py-2.5 text-sm outline-none focus:border-primary"
          />
          <button
            type="button"
            onClick={() => onChange(EMPTY_COMBO)}
            className="shrink-0 cursor-pointer rounded-xl border border-[var(--k-border-strong)] px-3 text-[12px] text-[var(--k-text-secondary)] hover:border-primary"
          >
            اختر من القائمة
          </button>
        </div>

        <span className="text-[11px] text-[var(--k-text-muted)]">{otherHint}</span>
      </div>
    )
  }

  return (
    <div ref={rootRef} className="relative flex flex-col gap-1.5">
      <label className="text-[13px] font-medium text-ink" htmlFor={listId}>
        {label}
      </label>

      <input
        id={listId}
        role="combobox"
        aria-expanded={open}
        aria-controls={`${listId}-list`}
        aria-autocomplete="list"
        autoComplete="off"
        disabled={disabled}
        placeholder={selected?.label ?? placeholder}
        value={open ? query : (selected?.label ?? '')}
        onFocus={() => setOpen(true)}
        onChange={(e) => {
          setQuery(e.target.value)
          setActive(0)
          setOpen(true)
        }}
        onKeyDown={onKeyDown}
        className={`cursor-pointer rounded-xl border border-[var(--k-border-strong)] bg-surface px-3.5 py-2.5 text-sm outline-none focus:border-primary disabled:cursor-not-allowed disabled:opacity-60 ${
          selected === null ? 'placeholder:text-[var(--k-text-muted)]' : 'placeholder:text-ink'
        }`}
      />

      {hint !== undefined && <span className="text-[11px] text-[var(--k-text-muted)]">{hint}</span>}

      {open && (
        <ul
          id={`${listId}-list`}
          role="listbox"
          className="absolute top-full z-20 mt-1 max-h-60 w-full overflow-auto rounded-xl border border-[var(--k-border-strong)] bg-surface py-1 shadow-[var(--k-sh-2)]"
        >
          {matches.map((option, i) => {
            const isOtherRow = option.id === OTHER

            return (
              <li key={option.key}>
                <button
                  type="button"
                  role="option"
                  aria-selected={option.id === value.id}
                  onMouseEnter={() => setActive(i)}
                  onClick={() => choose(option)}
                  className={`flex w-full cursor-pointer items-center justify-between px-3.5 py-2 text-right text-sm ${
                    i === active ? 'bg-mint' : ''
                  } ${isOtherRow ? 'border-t border-[var(--k-divider)] font-medium text-primary' : 'text-ink'}`}
                >
                  <span>{option.label}</span>
                  {option.id === value.id && <span className="text-primary">✓</span>}
                </button>
              </li>
            )
          })}

          {matches.length === 1 && (
            <li className="px-3.5 py-2 text-[12px] text-[var(--k-text-muted)]">
              لا نتيجة لـ «{query}» — أضفها عبر «أخرى».
            </li>
          )}
        </ul>
      )}
    </div>
  )
}

/**
 * تطبيعٌ للبحث العربي: «الدوحة» يجدها من كتب «دوحه» أو «الدوحه».
 *
 * بلا هذا يفشل البحث على فروقٍ لا يقصدها أحد — الهمزات والتاء المربوطة
 * والتشكيل — فيظنّ المستخدم أنّ مدينته غير موجودة، ويقترحها من جديد.
 */
function normalize(text: string): string {
  return text
    .trim()
    .toLowerCase()
    .replaceAll(/[ً-ْ]/g, '') // التشكيل
    .replaceAll(/[أإآٱ]/g, 'ا')
    .replaceAll('ى', 'ي')
    .replaceAll('ة', 'ه')
    .replaceAll('ـ', '') // التطويل
}
