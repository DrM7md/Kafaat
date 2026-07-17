import { useRef, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { api, apiErrorMessage } from '@/shared/api/client'
import { usePublicSettings } from '@/shared/hooks/usePublicSettings'

export type Attachment = {
  uuid: string
  type: 'cv' | 'certificate' | 'good_conduct' | 'logo'
  original_name: string
  mime: string
  size: number
  url: string
}

type UploadType = 'cv' | 'good_conduct'

/** الصيغ التي يقبلها الخادم. أي توسيعٍ هنا بلا توسيعه هناك يُنتج 422. */
const ACCEPT = '.pdf,.png,.jpg,.jpeg,.docx,.doc'

const MAX_BYTES = 5 * 1024 * 1024

export function Attachments({ attachments }: { attachments: Attachment[] }) {
  const { data: settings } = usePublicSettings()

  // ADR-013: الميزة معطّلة حتى مراجعة قانونية. الخادم يرفض الرفع، فلا نعرض
  // للموظّف زرّاً يُحبطه. تفعيلها نقرةٌ في اللوحة — لا نشرُ كود.
  const goodConductEnabled = settings?.['features.good_conduct_upload'] === true

  return (
    <section className="flex flex-col gap-4 rounded-[24px] bg-surface p-5 shadow-[var(--k-sh-2)]">
      <div>
        <h2 className="font-heading text-lg text-ink">المرفقات</h2>
        <p className="mt-1 text-[12.5px] text-[var(--k-text-secondary)]">
          محجوبة عن الشركات حتى تدفع رسم الكشف — كما اسمك تماماً.
        </p>
      </div>

      <UploadSlot
        type="cv"
        title="السيرة الذاتية"
        note="مطلوبة لاكتمال ملفّك."
        attachments={attachments}
      />

      {goodConductEnabled ? (
        <UploadSlot
          type="good_conduct"
          title="شهادة حُسن السيرة والسلوك"
          note="اختيارية. ترفعها بنفسك — المنصّة وسيط تخزين لا جهة تحقّق."
          attachments={attachments}
        />
      ) : (
        <div className="rounded-xl border border-dashed border-[var(--k-border-strong)] p-4">
          <p className="text-[13px] font-medium text-ink">شهادة حُسن السيرة والسلوك</p>
          <p className="mt-1 text-[12px] text-[var(--k-text-muted)]">
            معطّلة حالياً بانتظار مراجعة قانونية. سنُفعّلها دون أن تفعل شيئاً.
          </p>
        </div>
      )}
    </section>
  )
}

function UploadSlot({
  type,
  title,
  note,
  attachments,
}: {
  type: UploadType
  title: string
  note: string
  attachments: Attachment[]
}) {
  const queryClient = useQueryClient()
  const inputRef = useRef<HTMLInputElement>(null)
  const [error, setError] = useState<string | null>(null)

  const existing = attachments.filter((a) => a.type === type)

  const upload = useMutation({
    mutationFn: async (file: File) => {
      const body = new FormData()
      body.append('type', type)
      body.append('file', file)

      return api.post('/profile/attachments', body)
    },
    onSuccess: async () => {
      setError(null)
      await queryClient.invalidateQueries({ queryKey: ['profile'] })
    },
    onError: (err) => setError(apiErrorMessage(err)),
  })

  const remove = useMutation({
    mutationFn: async (uuid: string) => api.delete(`/profile/attachments/${uuid}`),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['profile'] })
    },
    onError: (err) => setError(apiErrorMessage(err)),
  })

  function pick(file: File | undefined) {
    if (file === undefined) return

    // نفحص الحجم هنا أيضاً لا ثقةً بالعميل، بل لطفاً به: رفعُ 40 ميغابايت
    // ثم ردُّها بـ 422 إهدارُ وقتٍ وباقة بيانات.
    if (file.size > MAX_BYTES) {
      setError('الحدّ الأقصى 5 ميغابايت.')
      return
    }

    setError(null)
    upload.mutate(file)
  }

  return (
    <div className="rounded-xl border border-[var(--k-border-strong)] p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-[13px] font-medium text-ink">{title}</p>
          <p className="mt-0.5 text-[11px] text-[var(--k-text-muted)]">{note}</p>
        </div>

        <button
          type="button"
          disabled={upload.isPending}
          onClick={() => inputRef.current?.click()}
          className="cursor-pointer rounded-xl bg-surface-muted px-3.5 py-2 text-[12.5px] font-semibold text-ink hover:brightness-95 disabled:cursor-wait disabled:opacity-60"
        >
          {upload.isPending ? 'جارٍ الرفع…' : existing.length > 0 ? 'رفع نسخة أحدث' : 'اختر ملفاً'}
        </button>

        <input
          ref={inputRef}
          type="file"
          accept={ACCEPT}
          hidden
          onChange={(e) => {
            pick(e.target.files?.[0])
            // نُفرغ الحقل: بلا هذا لا يُطلق اختيارُ الملفّ نفسه مرّتين حدثاً.
            e.target.value = ''
          }}
        />
      </div>

      {existing.length > 0 && (
        <ul className="mt-3 flex flex-col gap-2 border-t border-[var(--k-divider)] pt-3">
          {existing.map((file) => (
            <li key={file.uuid} className="flex items-center justify-between gap-3">
              <a
                href={file.url}
                className="min-w-0 flex-1 truncate text-[12.5px] font-medium"
                title={file.original_name}
              >
                {file.original_name}
              </a>

              <span className="numeric shrink-0 text-[11px] text-[var(--k-text-muted)]">
                {formatSize(file.size)}
              </span>

              <button
                type="button"
                disabled={remove.isPending}
                onClick={() => remove.mutate(file.uuid)}
                className="shrink-0 cursor-pointer text-[11px] text-[#8a2b2b] hover:underline disabled:opacity-50"
              >
                حذف
              </button>
            </li>
          ))}
        </ul>
      )}

      <p className="mt-3 text-[11px] text-[var(--k-text-muted)]">
        PDF أو PNG أو JPG أو DOCX · حتى 5 ميغابايت
      </p>

      {error !== null && <p className="mt-2 text-[12px] text-[#96682A]">{error}</p>}
    </div>
  )
}

function formatSize(bytes: number): string {
  const kb = bytes / 1024

  // ملفٌّ أصغر من كيلوبايت يُقرَّب إلى صفر، فيبدو «0 ك.ب» عطباً لا حجماً.
  if (kb < 1024) return `${Math.max(1, Math.round(kb))} ك.ب`

  return `${(kb / 1024).toFixed(1)} م.ب`
}
