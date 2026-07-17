/**
 * تفضيل المظهر — اختيار خطوط العناوين والشعار والنصّ.
 *
 * تفضيلٌ شخصيّ يُحفظ في متصفّح المستخدم (localStorage) ويُطبَّق وقت التشغيل
 * بتجاوز متغيّرات CSS على `:root` — فلا خادمَ ولا هجرة قاعدة بيانات. لأنّ
 * الرموز تُقرأ عبر `var(--k-font-*)` و`var(--font-*)` في كل مكان، يكفي أن
 * نكتبها على `documentElement` (الأنماط السطرية تعلو قواعد `@theme`) ليتغيّر
 * الخطّ في المنصّة كلّها فوراً.
 */

export type FontId = 'tajawal' | 'reem-kufi' | 'cairo' | 'alexandria' | 'ibm-plex'

/** الخطوط المتاحة — كلّها محمَّلة في index.html. */
export const FONTS: Record<FontId, { label: string; stack: string }> = {
  tajawal: { label: 'تجوّل', stack: "'Tajawal', system-ui, sans-serif" },
  'reem-kufi': { label: 'ريم كوفي', stack: "'Reem Kufi', system-ui, sans-serif" },
  cairo: { label: 'القاهرة', stack: "'Cairo', system-ui, sans-serif" },
  alexandria: { label: 'الإسكندرية', stack: "'Alexandria', system-ui, sans-serif" },
  'ibm-plex': { label: 'IBM Plex عربي', stack: "'IBM Plex Sans Arabic', system-ui, sans-serif" },
}

const FONT_IDS = Object.keys(FONTS) as FontId[]

/** الخانات الثلاث القابلة للتخصيص. */
export type FontSlot = 'heading' | 'logo' | 'body'

export type Appearance = Record<FontSlot, FontId>

export const DEFAULT_APPEARANCE: Appearance = {
  heading: 'tajawal',
  logo: 'reem-kufi',
  body: 'ibm-plex',
}

/** وصفٌ لكل خانة — يُبنى منه منتقي صفحة «المظهر». */
export const SLOTS: { slot: FontSlot; title: string; hint: string; sample: string }[] = [
  { slot: 'heading', title: 'خطّ العناوين', hint: 'عناوين الصفحات والأقسام.', sample: 'تصفّح الكفاءات' },
  { slot: 'logo', title: 'خطّ الشعار', hint: 'شعار «كفاءات» في الترويسة وصفحات الدخول.', sample: 'كفاءات' },
  { slot: 'body', title: 'خطّ النصّ', hint: 'نصوص الواجهة والفقرات كلّها.', sample: 'ملفّاتٌ مهنية بهويّةٍ محجوبة.' },
]

const KEY = 'kafaat.appearance'

function isFontId(value: unknown): value is FontId {
  return typeof value === 'string' && FONT_IDS.includes(value as FontId)
}

/** يقرأ التفضيل المحفوظ، ويعود إلى الافتراضي عند غيابه أو تلفه أو قيمةٍ مجهولة. */
export function loadAppearance(): Appearance {
  try {
    const raw = localStorage.getItem(KEY)
    if (raw === null) return DEFAULT_APPEARANCE

    const parsed = JSON.parse(raw) as Partial<Record<FontSlot, unknown>>
    return {
      heading: isFontId(parsed.heading) ? parsed.heading : DEFAULT_APPEARANCE.heading,
      logo: isFontId(parsed.logo) ? parsed.logo : DEFAULT_APPEARANCE.logo,
      body: isFontId(parsed.body) ? parsed.body : DEFAULT_APPEARANCE.body,
    }
  } catch {
    // localStorage محجوب (وضع خاص) أو JSON تالف — لا نُسقط التطبيق لأجل خطّ.
    return DEFAULT_APPEARANCE
  }
}

export function saveAppearance(appearance: Appearance): void {
  try {
    localStorage.setItem(KEY, JSON.stringify(appearance))
  } catch {
    /* التخزين محجوب — يبقى التطبيق ضمن الجلسة، ولا نُزعج المستخدم بخطأ. */
  }
}

/**
 * يكتب الخطوط المختارة على `:root`. نكتب المتغيّرين (رمز التصميم `--k-font-*`
 * المستعمل مباشرةً في CSS، ورمز Tailwind `--font-*` الذي تُولَّد منه صنفيّات
 * `font-heading`/`font-logo`/…) ليتناسق كلّ استعمال.
 */
export function applyAppearance(appearance: Appearance): void {
  const root = document.documentElement.style
  const pairs: [FontSlot, string][] = [
    ['heading', '--font-heading'],
    ['logo', '--font-logo'],
    ['body', '--font-body'],
  ]

  for (const [slot, themeVar] of pairs) {
    const stack = FONTS[appearance[slot]].stack
    root.setProperty(themeVar, stack)
    root.setProperty(`--k-font-${slot}`, stack)
  }
}
