import { Link } from 'react-router'
import { useAuth } from '@/shared/auth/AuthProvider'
import { prefetchRouteChunk } from '@/app/lazyRoutes'
import type { Permission } from '@/shared/api/types'

/**
 * لوحة الإعدادات — بابٌ واحد لكل ما يُدار.
 *
 * ★ القوائم والأسعار والتدقيق غادرت شريط التنقّل عمداً. الشريط للمسار
 *   **اليومي** (تصفّح، كشوفات، طلبات)، وهذه صفحاتٌ تُفتح مرّةً في الشهر —
 *   وجودها هناك كان يُزاحم ما يُفتح كل يوم بما لا يُفتح إلّا نادراً.
 *
 * البطاقة تظهر لمن يملك صلاحيتها وحده: من لا يملك `audit.view` لا يرى بطاقة
 * التدقيق أصلاً، فلا يقع على بابٍ يُغلق في وجهه.
 */
type Card = {
  to: string
  title: string
  body: string
  icon: string
  /** غيابها ⇒ بطاقةٌ يراها كلّ من يبلغ اللوحة (كالمظهر — تفضيلٌ شخصيّ). */
  permission?: Permission
}

const CARDS: Card[] = [
  {
    to: '/admin/settings/system',
    title: 'إعدادات النظام',
    body: 'قواعد العمل نفسها: نموذج الربح، الحقول المحجوبة، سياسة الاسترداد، الخصوصية.',
    icon: '⚙️',
    permission: 'settings.view',
  },
  {
    to: '/admin/lookups',
    title: 'القوائم المرجعية',
    body: 'المجالات والمدن والدول والحالات — وما يقترحه المستخدمون عبر «أخرى».',
    icon: '📋',
    permission: 'lookup.manage',
  },
  {
    to: '/admin/pricing',
    title: 'أسعار الكشف',
    body: 'سعر كشف كل مجال. فعلٌ ماليّ — الفواتير السابقة محميّة بسعرها المجمّد.',
    icon: '💰',
    permission: 'settings.view',
  },
  {
    to: '/admin/audit',
    title: 'سجلّ التدقيق',
    body: 'كل فعلٍ حسّاس: من فعله، ومتى، وبأيّ قيمةٍ قبل وبعد.',
    icon: '🛡️',
    permission: 'audit.view',
  },
  {
    to: '/admin/settings/appearance',
    title: 'المظهر',
    body: 'خطوط العناوين والشعار والنصّ — تفضيلٌ يُحفظ في متصفّحك ويُطبَّق فوراً.',
    icon: '🎨',
  },
]

export function SettingsHubPage() {
  const { can } = useAuth()

  const visible = CARDS.filter((card) => card.permission === undefined || can(card.permission))

  return (
    <div className="mx-auto flex max-w-4xl flex-col gap-6">
      <header>
        <h1 className="font-heading text-2xl text-ink">الإعدادات</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          ما تُدار به المنصّة. كل تعديلٍ هنا يُسجَّل في سجلّ التدقيق بقيمته قبل وبعد.
        </p>
      </header>

      <div className="grid gap-4 sm:grid-cols-2">
        {visible.map((card) => (
          <SettingsCard key={card.to} card={card} />
        ))}
      </div>
    </div>
  )
}

function SettingsCard({ card }: { card: Card }) {
  // تمرير المؤشّر نيّةُ نقر: نُنزّل جزء الصفحة قبل أن تصل النقرة.
  const prefetch = () => prefetchRouteChunk[card.to]?.()

  return (
    <Link
      to={card.to}
      onMouseEnter={prefetch}
      onFocus={prefetch}
      className="flex items-start gap-4 rounded-[24px] bg-surface p-5 shadow-[var(--k-sh-2)] transition-[transform,box-shadow] duration-200 hover:-translate-y-1 hover:shadow-[var(--k-shadow-hover)]"
    >
      <span
        className="grid size-11 shrink-0 place-items-center rounded-[13px] bg-mint text-[19px]"
        aria-hidden
      >
        {card.icon}
      </span>

      <span className="min-w-0">
        <span className="block font-heading text-[15px] text-ink">{card.title}</span>
        <span className="mt-1 block text-[12.5px] leading-6 text-[var(--k-text-secondary)]">
          {card.body}
        </span>
      </span>
    </Link>
  )
}
