import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiErrorMessage } from '@/shared/api/client'
import type { Permission } from '@/shared/api/types'

type Role = {
  id: number
  name: string
  permissions: Permission[]
  users_count: number
  is_system: boolean
}

type RolesResponse = {
  roles: Role[]
  catalog: Record<string, Permission[]>
  critical: Permission[]
}

/**
 * مصفوفة دور × صلاحية.
 *
 * • حفظ تفاضلي: الخادم يسجّل ما أُضيف وما نُزع بدقّة في audit_logs.
 * • الصلاحيات الحرجة 🔴 مميّزة بصرياً وتحذّر قبل المنح.
 * • الحارسان (لا تنزع rbac.manage عن نفسك، لا تحذف دوراً بنيوياً) يفرضهما
 *   الخادم. ما هنا تلميحٌ بصري لا حماية. Brain/04-rbac.md §7.
 */
export function RoleMatrixPage() {
  const queryClient = useQueryClient()
  const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null)
  const [draft, setDraft] = useState<Set<Permission> | null>(null)

  const { data, isPending, isError } = useQuery({
    queryKey: ['admin', 'roles'],
    queryFn: async () => (await api.get<RolesResponse>('/admin/roles')).data,
  })

  const save = useMutation({
    mutationFn: async ({ roleId, permissions }: { roleId: number; permissions: Permission[] }) =>
      api.put(`/admin/roles/${roleId}/permissions`, { permissions }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin', 'roles'] })
      await queryClient.invalidateQueries({ queryKey: ['me'] })
      setDraft(null)
      setSelectedRoleId(null)
    },
  })

  if (isError) {
    return (
      <div className="grid place-items-center rounded-[20px] border border-dashed border-[var(--k-border-strong)] p-12 text-center">
        <p className="font-heading text-lg text-ink">تعذّر تحميل الأدوار</p>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">تأكّد من الاتصال ثم أعد المحاولة.</p>
      </div>
    )
  }

  if (isPending || data === undefined) {
    return <p className="py-16 text-center text-sm text-[var(--k-text-muted)]">جارٍ التحميل…</p>
  }

  const selected = data.roles.find((r) => r.id === selectedRoleId) ?? null
  const current = draft ?? new Set(selected?.permissions ?? [])
  const isCritical = (p: Permission) => data.critical.includes(p)

  function toggle(permission: Permission) {
    const next = new Set(current)
    next.has(permission) ? next.delete(permission) : next.add(permission)
    setDraft(next)
  }

  return (
    <div className="flex flex-col gap-6">
      <header>
        <h1 className="font-heading text-2xl text-ink">الأدوار والصلاحيات</h1>
        <p className="mt-1 text-sm text-[var(--k-text-secondary)]">
          تُنشأ الأدوار وتُعدَّل من هنا دون أي تعديل برمجي. الصلاحيات الحرجة{' '}
          <span className="text-[#96682A]">🔴</span> تُسجَّل كل ممارسة لها في سجلّ التدقيق.
        </p>
      </header>

      <div className="grid gap-6 md:grid-cols-[260px_1fr]">
        <aside className="flex flex-col gap-2">
          {data.roles.map((role) => (
            <button
              key={role.id}
              type="button"
              onClick={() => {
                setSelectedRoleId(role.id)
                setDraft(null)
              }}
              className={`cursor-pointer rounded-xl border p-3.5 text-right transition-colors ${
                role.id === selectedRoleId
                  ? 'border-primary bg-mint'
                  : 'border-[var(--k-border)] bg-surface hover:bg-surface-muted'
              }`}
            >
              <div className="flex items-center justify-between">
                <span className="text-[13px] font-semibold text-ink">{role.name}</span>
                {role.is_system && (
                  <span className="rounded bg-surface-muted px-1.5 py-0.5 text-[9px] text-[var(--k-text-muted)]">
                    بنيويّ
                  </span>
                )}
              </div>
              <p className="mt-1 numeric text-[11px] text-[var(--k-text-muted)]">
                {role.permissions.length} صلاحية · {role.users_count} مستخدم
              </p>
            </button>
          ))}
        </aside>

        <section className="rounded-[24px] bg-surface p-5 shadow-[var(--k-sh-2)]">
          {selected === null ? (
            <p className="py-12 text-center text-sm text-[var(--k-text-muted)]">اختر دوراً لتعديل صلاحياته.</p>
          ) : selected.name === 'super-admin' ? (
            <p className="py-12 text-center text-sm text-[var(--k-text-secondary)]">
              المشرف العام يملك كل الصلاحيات عبر <code>Gate::before</code>، ولا تُسنَد إليه صراحةً.
            </p>
          ) : (
            <>
              {save.isError && (
                <p className="mb-4 rounded-xl border border-[rgb(180_134_59/0.35)] bg-gold-tint p-3 text-sm text-[#96682A]">
                  {apiErrorMessage(save.error)}
                </p>
              )}

              <div className="flex flex-col gap-5">
                {Object.entries(data.catalog).map(([group, permissions]) => (
                  <div key={group}>
                    <h2 className="mb-2 text-[13px] font-semibold text-ink">{group}</h2>
                    <div className="grid gap-1.5 sm:grid-cols-2">
                      {permissions.map((permission) => (
                        <label
                          key={permission}
                          className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-surface-muted"
                        >
                          <input
                            type="checkbox"
                            checked={current.has(permission)}
                            onChange={() => toggle(permission)}
                            className="size-4 accent-[var(--k-primary)]"
                          />
                          <code dir="ltr" className="text-[11.5px] text-[var(--k-text-secondary)]">
                            {permission}
                          </code>
                          {isCritical(permission) && <span title="صلاحية حرجة">🔴</span>}
                        </label>
                      ))}
                    </div>
                  </div>
                ))}
              </div>

              <div className="mt-6 flex items-center gap-3 border-t border-[var(--k-divider)] pt-4">
                <button
                  type="button"
                  disabled={draft === null || save.isPending}
                  onClick={() => save.mutate({ roleId: selected.id, permissions: [...current] })}
                  className="cursor-pointer rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-[var(--k-shadow-primary)] transition-[transform,filter] duration-200 hover:-translate-y-0.5 hover:brightness-110 active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  {save.isPending ? 'جارٍ الحفظ…' : 'حفظ التغييرات'}
                </button>

                {draft !== null && (
                  <button
                    type="button"
                    onClick={() => setDraft(null)}
                    className="cursor-pointer text-sm text-[var(--k-text-secondary)]"
                  >
                    تراجع
                  </button>
                )}
              </div>
            </>
          )}
        </section>
      </div>
    </div>
  )
}
