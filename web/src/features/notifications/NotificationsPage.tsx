import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router'
import { Can } from '@/shared/auth/Can'
import { NotificationRow } from './NotificationRow'
import {
  notificationsQuery,
  useMarkAllRead,
  useMarkRead,
  useTracksFollowUp,
  type AppNotification,
  type Tab,
} from './queries'

/**
 * صفحة الإشعارات — **ليست** صندوق الوارد.
 *
 * الوارد صار قائمة الجرس (`NotificationBell`): الجديد ينزل فوق مكان المستخدم،
 * يقرأ وينقر ويعود. فما بقي لهذه الصفحة وجهان بحسب من يفتحها:
 *
 * • المشرف — طابورُ عمل: تبويبتا «جديد» و«تمّ»، فيعرف ما تابعه وما بقي.
 * • الموظّف والشركة — أرشيفُ ما قرأوه لا غير. الجديد مكانه الجرس، وعرضُه هنا
 *   مرّةً ثانية يجعل للإشعار الواحد صندوقين لا يدري المستخدم أيّهما الأصل.
 */
export function NotificationsPage() {
  const tracksFollowUp = useTracksFollowUp()

  return tracksFollowUp ? <FollowUpInbox /> : <ReadArchive />
}

/** وجه المشرف: طابور قرارات — «جديد» ما ينتظره، و«تمّ» ما تابعه. */
function FollowUpInbox() {
  const navigate = useNavigate()
  const [tab, setTab] = useState<Tab>('new')

  const { data, isPending } = useQuery(notificationsQuery(tab))
  const markRead = useMarkRead()
  const markAllRead = useMarkAllRead()

  const rows = data?.data ?? []
  const unread = data?.unread_count ?? 0

  function open(notification: AppNotification) {
    if (notification.read_at === null) {
      markRead.mutate(notification.id)
    }

    if (notification.link !== null) {
      void navigate(notification.link)
    }
  }

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-5">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="font-heading text-2xl text-ink">صندوق المتابعة</h1>
          <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
            ما يستدعي قراراً منك. النقر يفتح الوجهة وينقله إلى «تمّ».
          </p>
        </div>

        {unread > 0 && (
          <button
            type="button"
            disabled={markAllRead.isPending}
            onClick={() => markAllRead.mutate()}
            className="cursor-pointer rounded-xl border border-[var(--k-border-strong)] px-3.5 py-2 text-[12.5px] font-medium hover:border-primary disabled:opacity-50"
          >
            تعليم الكل كمنجَز
          </button>
        )}
      </header>

      <div className="flex gap-2">
        <TabButton active={tab === 'new'} onClick={() => setTab('new')}>
          جديد {unread > 0 && <Count value={unread} />}
        </TabButton>
        <TabButton active={tab === 'done'} onClick={() => setTab('done')}>
          تمّ
        </TabButton>
      </div>

      <List
        isPending={isPending}
        rows={rows}
        onOpen={open}
        empty={
          tab === 'new'
            ? { title: 'لا جديد', body: 'لا شيء ينتظر قرارك الآن.' }
            : { title: 'لم تُتابع شيئاً بعد', body: 'ما تفتحه من الإشعارات يظهر هنا.' }
        }
      />

      {/* الإشعارات ما يستدعي قراراً، والسجلّ الكامل مكانٌ آخر. حارسٌ لازم —
          لا كلّ من يتابع إشعاراً يملك `audit.view`، ورابطٌ مكشوفٌ لمن لا
          يملكها يقوده إلى صفحةٍ تردّه عنها. */}
      <Can permission="audit.view">
        <p className="text-center text-[11px] text-[var(--k-text-muted)]">
          هنا ما يستدعي قراراً منك. السجلّ الكامل لكل ما يجري في المنصّة في{' '}
          <Link to="/admin/audit">سجلّ التدقيق</Link>.
        </p>
      </Can>
    </div>
  )
}

/**
 * وجه الموظّف والشركة: ما قرأوه فقط.
 *
 * لا زرّ «تعليم الكل» هنا ولا نقطةَ «جديد»: كلّ ما في الصفحة مقروءٌ أصلاً.
 * النقر يفتح الوجهة لا أكثر — رجوعٌ إلى خبرٍ سبق أن رآه.
 */
function ReadArchive() {
  const navigate = useNavigate()

  const { data, isPending } = useQuery(notificationsQuery('done'))
  const rows = data?.data ?? []

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-5">
      <header>
        <h1 className="font-heading text-2xl text-ink">الإشعارات المقروءة</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          ما سبق أن فتحته. الجديد يصلك على 🔔 في أعلى الصفحة.
        </p>
      </header>

      <List
        isPending={isPending}
        rows={rows}
        onOpen={(notification) => {
          if (notification.link !== null) void navigate(notification.link)
        }}
        empty={{ title: 'لم تقرأ إشعاراً بعد', body: 'ما تفتحه من الجرس يُحفظ هنا.' }}
      />
    </div>
  )
}

function List({
  isPending,
  rows,
  onOpen,
  empty,
}: {
  isPending: boolean
  rows: AppNotification[]
  onOpen: (notification: AppNotification) => void
  empty: { title: string; body: string }
}) {
  if (isPending) {
    return <p className="py-16 text-center text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>
  }

  if (rows.length === 0) {
    return (
      <div className="grid place-items-center rounded-[20px] border border-dashed border-[var(--k-border-strong)] p-14 text-center">
        <p className="font-heading text-base text-ink">{empty.title}</p>
        <p className="mt-1 text-[13px] text-[var(--k-text-secondary)]">{empty.body}</p>
      </div>
    )
  }

  return (
    <ul className="flex flex-col gap-2">
      {rows.map((row) => (
        <li key={row.id}>
          <NotificationRow notification={row} onOpen={() => onOpen(row)} />
        </li>
      ))}
    </ul>
  )
}

function TabButton({
  active,
  onClick,
  children,
}: {
  active: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-4 py-2 text-[13px] font-medium ${
        active
          ? 'border-primary bg-mint text-primary'
          : 'border-[var(--k-border-strong)] bg-surface text-[var(--k-text-secondary)]'
      }`}
    >
      {children}
    </button>
  )
}

function Count({ value }: { value: number }) {
  return (
    <span className="numeric rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white">
      {value}
    </span>
  )
}
