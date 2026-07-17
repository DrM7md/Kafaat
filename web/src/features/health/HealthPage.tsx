import { useQuery } from '@tanstack/react-query'
import { api } from '@/shared/api/client'

type Health = {
  status: string
  service: string
  version: string
  database: string
}

export function HealthPage() {
  const { data, isPending, isError } = useQuery({
    queryKey: ['health'],
    queryFn: async () => (await api.get<Health>('/health')).data,
  })

  return (
    <main className="min-h-screen grid place-items-center p-6">
      <div className="w-full max-w-lg rounded-[24px] bg-surface p-8 shadow-[var(--k-sh-2)]">
        <header className="flex items-baseline gap-3">
          <span className="font-logo text-3xl font-bold text-primary">كفاءات</span>
          <span className="text-xs tracking-[1px] text-[var(--k-text-muted)]">KAFAAT</span>
        </header>

        <p className="mt-2 text-sm text-[var(--k-text-secondary)]">
          حيث تجد الشركةُ موظّفَها المناسب.
        </p>

        <div className="my-6 h-px bg-[var(--k-divider)]" />

        <h1 className="text-base font-semibold">حالة الخدمة</h1>

        <div className="mt-4 rounded-[14px] bg-surface-muted p-4 text-sm">
          {isPending && <p className="text-[var(--k-text-muted)]">جارٍ الفحص…</p>}

          {isError && (
            <p className="text-[#96682a]">
              تعذّر الوصول إلى الـ API. تأكّد من تشغيل <code>php artisan serve</code>.
            </p>
          )}

          {data && (
            <dl className="grid grid-cols-2 gap-y-2">
              <dt className="text-[var(--k-text-muted)]">الخدمة</dt>
              <dd className="font-medium">{data.service}</dd>
              <dt className="text-[var(--k-text-muted)]">الإصدار</dt>
              <dd className="numeric font-medium">{data.version}</dd>
              <dt className="text-[var(--k-text-muted)]">قاعدة البيانات</dt>
              <dd className="font-medium">{data.database === 'up' ? 'متّصلة' : 'منقطعة'}</dd>
            </dl>
          )}
        </div>

        <div className="mt-6 flex items-center gap-2">
          <span className="text-xs text-[var(--k-text-muted)]">اللوحة اللونية</span>
          <div className="flex gap-2">
            {[
              ['var(--k-primary)', 'بترولي'],
              ['var(--k-primary-dark)', 'بترولي غامق'],
              ['var(--k-gold)', 'ذهبي — الكشف'],
              ['var(--k-canvas)', 'قماشة'],
              ['var(--k-ink)', 'حبر'],
            ].map(([color, title]) => (
              <span
                key={title}
                title={title}
                className="size-7 rounded-[9px] border border-[var(--k-border)]"
                style={{ background: color }}
              />
            ))}
          </div>
        </div>
      </div>
    </main>
  )
}
