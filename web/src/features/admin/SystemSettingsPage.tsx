import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiErrorMessage } from '@/shared/api/client'
import { BackToSettings } from './BackToSettings'

type Setting = {
  key: string
  value: unknown
  type: 'string' | 'int' | 'bool' | 'json' | 'enum' | 'array'
  group: string
  options: string[] | null
  is_public: boolean
  description: string | null
}

const GROUP_LABELS: Record<string, string> = {
  billing: 'نموذج الربح',
  masking: 'حجب الحقول',
  refund: 'سياسة الاسترداد',
  privacy: 'الخصوصية',
  features: 'مفاتيح الميزات',
  platform: 'المنصّة',
}

/** المجموعات التي يُفسد تعديلُها المنتجَ إن أُسيء. */
const DANGEROUS = new Set(['masking', 'billing'])

/**
 * إعدادات النظام — قواعد العمل نفسها.
 *
 * ليست «الإعدادات» بإطلاق: تلك صارت لوحةً (`SettingsHubPage`) تجمع هذه
 * الصفحةَ والقوائمَ والأسعارَ والتدقيق. ما هنا هو ما يُغيّر **سلوك المنتج**
 * — نموذج الربح، الحجب، الاسترداد — لا ما يُدار من بياناتٍ مرجعية.
 */
export function SystemSettingsPage() {
  const queryClient = useQueryClient()
  const [activeGroup, setActiveGroup] = useState('billing')

  const { data, isPending, isError } = useQuery({
    queryKey: ['admin', 'settings'],
    queryFn: async () => (await api.get<{ settings: Record<string, Setting[]> }>('/admin/settings')).data.settings,
  })

  const save = useMutation({
    mutationFn: async (payload: { key: string; value: unknown; confirmed?: boolean }) =>
      api.put('/admin/settings', payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin', 'settings'] })
      await queryClient.invalidateQueries({ queryKey: ['me'] })
      await queryClient.invalidateQueries({ queryKey: ['candidates'] })
    },
  })

  if (isError) {
    return (
      <div className="grid place-items-center rounded-[20px] border border-dashed border-[var(--k-border-strong)] p-12 text-center">
        <p className="font-heading text-lg text-ink">تعذّر تحميل الإعدادات</p>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">تأكّد من الاتصال ثم أعد المحاولة.</p>
      </div>
    )
  }

  if (isPending || data === undefined) {
    return <p className="py-16 text-center text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>
  }

  const groups = Object.keys(data)
  const settings = data[activeGroup] ?? []

  return (
    <div className="flex flex-col gap-6">
      <header>
        <BackToSettings />
        <h1 className="font-heading text-2xl text-ink">إعدادات النظام</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          كل قاعدة عملٍ مؤثّرة تعيش هنا. تغييرها لا يحتاج نشر كود، وكل تعديل يُسجَّل بقيمته قبل وبعد.
        </p>
      </header>

      <nav className="flex flex-wrap gap-2">
        {groups.map((group) => (
          <button
            key={group}
            type="button"
            onClick={() => setActiveGroup(group)}
            className={`cursor-pointer rounded-full px-3.5 py-2 text-xs font-medium ${
              group === activeGroup
                ? 'bg-primary text-white'
                : 'border border-[var(--k-border-strong)] bg-surface text-[var(--k-text-secondary)]'
            }`}
          >
            {GROUP_LABELS[group] ?? group}
          </button>
        ))}
      </nav>

      {DANGEROUS.has(activeGroup) && (
        <div
          className="rounded-xl border p-3.5 text-[13px]"
          style={{ borderColor: 'rgb(200 60 60 / 0.35)', background: 'rgb(200 60 60 / 0.06)', color: '#8a2b2b' }}
        >
          ⚠️ <strong>تحذير:</strong>{' '}
          {activeGroup === 'masking'
            ? 'إزالة الاسم أو الهاتف أو البريد من الحقول المحجوبة تجعل بيانات التواصل مرئيةً للجميع بلا دفع — أي تُلغي نموذج الربح. الخادم يطلب تأكيداً صريحاً.'
            : 'تغيير نموذج الربح أو الرسم يؤثّر على كل عملية كشفٍ لاحقة. الفواتير السابقة محميّة: سعرها مجمّد وقت تنفيذها.'}
        </div>
      )}

      {save.isError && (
        <p className="rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
          {apiErrorMessage(save.error)}
        </p>
      )}

      <div className="flex flex-col gap-3">
        {settings.map((setting) => (
          <SettingRow key={setting.key} setting={setting} onSave={save.mutate} pending={save.isPending} />
        ))}
      </div>
    </div>
  )
}

function SettingRow({
  setting,
  onSave,
  pending,
}: {
  setting: Setting
  onSave: (p: { key: string; value: unknown; confirmed?: boolean }) => void
  pending: boolean
}) {
  const [value, setValue] = useState<unknown>(setting.value)
  const dirty = JSON.stringify(value) !== JSON.stringify(setting.value)

  return (
    <div className="flex flex-wrap items-center gap-4 rounded-[20px] bg-surface p-4 shadow-[var(--k-sh-2)]">
      <div className="min-w-[240px] flex-1">
        <code dir="ltr" className="text-[12px] font-semibold text-ink">
          {setting.key}
        </code>
        {setting.description !== null && (
          <p className="mt-1 text-[11.5px] leading-5 text-[var(--k-text-muted)]">{setting.description}</p>
        )}
      </div>

      <div className="min-w-[220px]">
        <SettingInput setting={setting} value={value} onChange={setValue} />
      </div>

      <button
        type="button"
        disabled={!dirty || pending}
        onClick={() =>
          onSave({
            key: setting.key,
            value,
            // الخادم يرفض رفع الحجب عن الاسم/الهاتف/البريد دون تأكيد صريح.
            confirmed: setting.key === 'masking.fields',
          })
        }
        className="cursor-pointer rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-30"
      >
        حفظ
      </button>
    </div>
  )
}

function SettingInput({
  setting,
  value,
  onChange,
}: {
  setting: Setting
  value: unknown
  onChange: (v: unknown) => void
}) {
  if (setting.type === 'bool') {
    return (
      <label className="flex cursor-pointer items-center gap-2 text-[13px]">
        <input
          type="checkbox"
          checked={value === true}
          onChange={(e) => onChange(e.target.checked)}
          className="size-4 accent-[var(--k-primary)]"
        />
        {value === true ? 'مفعّل' : 'معطّل'}
      </label>
    )
  }

  if (setting.type === 'enum' && setting.options !== null) {
    return (
      <select
        value={String(value)}
        onChange={(e) => onChange(e.target.value)}
        className="w-full cursor-pointer rounded-lg border border-[var(--k-border-strong)] bg-surface px-3 py-2 text-[13px]"
      >
        {setting.options.map((option) => (
          <option key={option} value={option}>
            {option}
          </option>
        ))}
      </select>
    )
  }

  if (setting.type === 'array') {
    return (
      <input
        dir="ltr"
        value={Array.isArray(value) ? value.join(', ') : ''}
        onChange={(e) => onChange(e.target.value.split(',').map((s) => s.trim()).filter(Boolean))}
        className="w-full rounded-lg border border-[var(--k-border-strong)] bg-surface px-3 py-2 text-[12px]"
      />
    )
  }

  return (
    <input
      type={setting.type === 'int' ? 'number' : 'text'}
      value={String(value)}
      onChange={(e) => onChange(setting.type === 'int' ? Number(e.target.value) : e.target.value)}
      className="w-full rounded-lg border border-[var(--k-border-strong)] bg-surface px-3 py-2 text-[13px]"
    />
  )
}
