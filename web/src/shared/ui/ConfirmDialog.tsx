import { useEffect, useRef, type ReactNode } from 'react'

/**
 * تأكيد الأفعال غير القابلة للتراجع بسهولة.
 *
 * يستعمل <dialog> الأصلي: يحبس التركيز، ويغلق بـ Escape، ويعمل مع قارئ
 * الشاشة بلا شيفرة إضافية.
 */
export function ConfirmDialog({
  open,
  title,
  body,
  confirmLabel = 'تأكيد',
  danger = false,
  pending = false,
  onConfirm,
  onCancel,
  children,
}: {
  open: boolean
  title: string
  body: string
  confirmLabel?: string
  danger?: boolean
  pending?: boolean
  onConfirm: () => void
  onCancel: () => void
  children?: ReactNode
}) {
  const ref = useRef<HTMLDialogElement>(null)

  useEffect(() => {
    const dialog = ref.current
    if (dialog === null) return

    if (open && !dialog.open) dialog.showModal()
    if (!open && dialog.open) dialog.close()
  }, [open])

  return (
    <dialog
      ref={ref}
      onCancel={(e) => {
        e.preventDefault()
        onCancel()
      }}
      className="m-auto w-full max-w-md rounded-[20px] border border-[var(--k-border)] bg-surface p-0 backdrop:bg-[rgb(22_33_31/0.45)] backdrop:backdrop-blur-sm"
    >
      <div className="p-6 text-right" dir="rtl">
        <h2 className="font-heading text-lg text-ink">{title}</h2>
        <p className="mt-2 text-[13px] leading-6 text-[var(--k-text-secondary)]">{body}</p>

        {children !== undefined && <div className="mt-4">{children}</div>}

        <div className="mt-6 flex items-center justify-end gap-2">
          <button
            type="button"
            onClick={onCancel}
            className="cursor-pointer rounded-xl border border-[var(--k-border-strong)] px-4 py-2.5 text-sm font-medium hover:bg-surface-muted"
          >
            إلغاء
          </button>

          <button
            type="button"
            disabled={pending}
            onClick={onConfirm}
            className={`cursor-pointer rounded-xl px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-50 ${
              danger ? 'bg-[#8a2b2b]' : 'bg-primary'
            }`}
          >
            {pending ? 'جارٍ…' : confirmLabel}
          </button>
        </div>
      </div>
    </dialog>
  )
}
