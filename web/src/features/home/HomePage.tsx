import { Link } from 'react-router'
import { useAuth } from '@/shared/auth/AuthProvider'
import { Can } from '@/shared/auth/Can'

export function HomePage() {
  const { me } = useAuth()

  return (
    <div className="mx-auto max-w-2xl text-center">
      <h1 className="font-heading text-3xl text-ink">
        أهلاً، {me?.user.name}
      </h1>

      <p className="mt-2 text-sm text-[var(--k-text-secondary)]">
        كفاءات — حيث تجد الشركةُ موظّفَها المناسب.
      </p>

      <div className="mt-8 grid gap-4 sm:grid-cols-2">
        <Can permission="candidate.browse">
          <Card
            to="/candidates"
            title="تصفّح الكفاءات"
            body="ملفّات مهنية كاملة، بهويّات محجوبة حتى تدفع رسم الكشف."
          />
        </Can>

        <Can permission="candidate.reveal">
          <Card to="/reveals" title="كشوفاتي" body="سجلّ عمليات الكشف والمبالغ المجمّدة وقت تنفيذها." />
        </Can>

        <Can permission="profile.update">
          <Card
            to="/profile"
            title="ملفّي المهني"
            body="اسمك وبيانات تواصلك محجوبة عن الشركات حتى تدفع رسم الكشف."
          />
        </Can>

        <Can permission="rbac.manage">
          <Card to="/admin/roles" title="الأدوار والصلاحيات" body="أنشئ أدواراً ووزّع الصلاحيات دون تعديل برمجي." />
        </Can>

        <Can permission="settings.view">
          <Card to="/admin/settings" title="الإعدادات" body="نموذج الربح، حجب الحقول، الخصوصية، مفاتيح الميزات." />
        </Can>

        <Can permission="audit.view">
          <Card to="/admin/audit" title="سجلّ التدقيق" body="سجلّ إلحاقيّ لكل فعلٍ حسّاس. لا يُعدَّل ولا يُحذَف." />
        </Can>
      </div>
    </div>
  )
}

function Card({ to, title, body }: { to: string; title: string; body: string }) {
  return (
    <Link
      to={to}
      className="rounded-[16px] border border-[var(--k-border)] bg-surface p-5 text-right transition-[transform,box-shadow] duration-200 hover:-translate-y-1 hover:shadow-[var(--k-shadow-hover)]"
    >
      <p className="font-heading text-base text-ink">{title}</p>
      <p className="mt-1.5 text-[12.5px] leading-6 text-[var(--k-text-secondary)]">{body}</p>
    </Link>
  )
}
