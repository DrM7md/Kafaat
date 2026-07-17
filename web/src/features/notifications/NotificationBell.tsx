import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { NotificationRow } from './NotificationRow'
import { useInbox, useMarkAllRead, useMarkRead, useTracksFollowUp, type AppNotification } from './queries'

/**
 * جرس الإشعارات — النقر يفتح **قائمةً منسدلة**، لا صفحة.
 *
 * الإشعار خبرٌ عابر: من يفتح صفحةً كاملة ليقرأه يفقد ما كان يعمل عليه. فالقائمة
 * تنزل فوق مكانه، ويقرأ، وينقر، ويعود.
 *
 * ما فيها هو **الجديد وحده**: النقر يُعلّم الإشعار مقروءاً فيغادر القائمة فوراً
 * (تحديثٌ تفاؤلي في `useMarkRead`) ثمّ ينتقل إلى وجهته. فما يبقى معروضاً هو
 * دائماً ما لم يُرَ بعد — وأرشيفُ ما قُرئ خلف رابطٍ في الذيل.
 *
 * ⚠️ لا نعرض «99+»: عدّادٌ لا يهبط أبداً يُدرَّب المستخدمُ على تجاهله.
 */
export function NotificationBell() {
  const navigate = useNavigate()
  const rootRef = useRef<HTMLDivElement>(null)

  const [open, setOpen] = useState(false)

  // ★ المشرف صندوقه طابور عمل: القائمة تُريه ما ينتظر قراره، والصفحة تُريه
  //   «جديد» و«تمّ» معاً فيعرف ماذا عالج. أمّا الموظّف والشركة فصفحتهما
  //   أرشيفُ ما قرأوه لا أكثر.
  const tracksFollowUp = useTracksFollowUp()

  const { data, isPending } = useInbox()
  const markRead = useMarkRead()
  const markAllRead = useMarkAllRead()

  const rows = data?.data ?? []
  const unread = data?.unread_count ?? 0

  // إغلاق بالنقر خارجها أو بـ Escape — قائمةٌ معلّقة فوق الصفحة تحجب ما تحتها.
  useEffect(() => {
    if (!open) return

    function onPointerDown(event: PointerEvent) {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false)
    }

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') setOpen(false)
    }

    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)

    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  function openNotification(notification: AppNotification) {
    markRead.mutate(notification.id)

    // بلا وجهة: يبقى الجرس مفتوحاً ليرى المستخدمَ الإشعارَ يغادر القائمة —
    // إغلاقٌ صامتٌ بلا انتقالٍ يبدو كأنّ النقرة ضاعت.
    if (notification.link !== null) {
      setOpen(false)
      void navigate(notification.link)
    }
  }

  return (
    <div ref={rootRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
        aria-haspopup="menu"
        aria-label={unread > 0 ? `الإشعارات — ${unread} جديدة` : 'الإشعارات'}
        className={`relative grid size-9 cursor-pointer place-items-center rounded-lg transition-colors ${
          open ? 'bg-mint text-primary' : 'text-[var(--k-text-secondary)] hover:bg-surface-muted'
        }`}
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden>
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" strokeLinecap="round" strokeLinejoin="round" />
          <path d="M13.7 21a2 2 0 0 1-3.4 0" strokeLinecap="round" strokeLinejoin="round" />
        </svg>

        {unread > 0 && (
          <span className="numeric absolute -left-0.5 -top-0.5 grid min-w-4 place-items-center rounded-full bg-primary px-1 text-[9px] font-bold text-white">
            {unread > 9 ? '9+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div
          role="menu"
          className="absolute start-0 top-[calc(100%+10px)] z-20 flex w-[min(23rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-[16px] border border-[var(--k-border-strong)] bg-surface shadow-[var(--k-sh-2)]"
        >
          <header className="flex items-center justify-between gap-2 border-b border-[var(--k-divider)] px-4 py-3">
            <span className="font-heading text-[15px] text-ink">الإشعارات</span>

            {unread > 0 && (
              <button
                type="button"
                disabled={markAllRead.isPending}
                onClick={() => markAllRead.mutate()}
                className="cursor-pointer text-[12px] font-medium text-primary hover:underline disabled:opacity-50"
              >
                تعليم الكل كمقروء
              </button>
            )}
          </header>

          {isPending ? (
            <p className="px-4 py-10 text-center text-[13px] text-[var(--k-text-muted)]">جارٍ التحميل…</p>
          ) : rows.length === 0 ? (
            <div className="px-4 py-10 text-center">
              <p className="text-[13.5px] font-medium text-ink">لا إشعارات جديدة</p>
              <p className="mt-1 text-[12px] text-[var(--k-text-secondary)]">
                سنُخبرك هنا حين يحدث ما يخصّك.
              </p>
            </div>
          ) : (
            <ul className="flex max-h-[min(28rem,60vh)] flex-col gap-1 overflow-y-auto p-2">
              {rows.map((row) => (
                <li key={row.id}>
                  <NotificationRow notification={row} onOpen={() => openNotification(row)} compact />
                </li>
              ))}
            </ul>
          )}

          <Link
            to="/notifications"
            onClick={() => setOpen(false)}
            className="border-t border-[var(--k-divider)] px-4 py-3 text-center text-[12.5px] font-medium text-primary hover:bg-surface-muted"
          >
            {tracksFollowUp ? 'صندوق المتابعة الكامل' : 'الإشعارات المقروءة'}
          </Link>
        </div>
      )}
    </div>
  )
}
