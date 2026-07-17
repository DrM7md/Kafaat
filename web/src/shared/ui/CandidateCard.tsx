import { Link } from 'react-router'
import type { Candidate } from '@/shared/api/types'
import { MaskedAvatar } from './MaskedAvatar'
import { SkillChip, StatusAge, StatusBadge } from './StatusBadge'
import { DemandBadge, ListedSince } from './DemandBadge'
import { RevealButton } from './RevealButton'
import { TiltCard } from './TiltCard'
import { formatSalary, formatYears } from '@/shared/lib/format'

/**
 * بطاقة الكفاءة — معالجة `1a` المعتمدة (ADR-008).
 * منقولة من UI/كفاءات - بطاقة الكفاءة (تنويعات).dc.html → #1a.
 *
 * ⚠️ لا تشوّش اسماً: الاسم غير موجود قبل الكشف. `candidate.name` تكون
 *    `undefined`، وTypeScript يجبرك على التعامل مع ذلك.
 */
export function CandidateCard({
  candidate,
  pending = false,
  onReveal,
  onPrefetch,
}: {
  candidate: Candidate
  pending?: boolean
  onReveal: (ref: string) => void
  /** يُستدعى عند نيّة الفتح (تمرير المؤشّر أو التركيز) — يجلب التفاصيل مسبقاً. */
  onPrefetch?: (ref: string) => void
}) {
  const revealed = candidate.is_revealed
  const prefetch = () => onPrefetch?.(candidate.ref)
  const hot = candidate.demand === 'high'

  return (
    <TiltCard
      onMouseEnter={prefetch}
      onFocusCapture={prefetch}
      onTouchStart={prefetch}
      className="group relative flex flex-col gap-3.5 overflow-hidden rounded-[24px] bg-surface p-5 shadow-[var(--k-sh-2)] transition-shadow duration-300 hover:shadow-[var(--k-sh-3)]"
    >
      {/* هالة الطلب المرتفع — توهّجٌ بتروليّ خافت في أعلى البطاقة، لا ذهبيّ
          (الذهبيّ محجوز لفعل الكشف). يظهر فقط لمن الطلبُ عليه «مرتفع». */}
      {hot && (
        <div
          aria-hidden
          className="pointer-events-none absolute inset-x-0 top-0 h-32"
          style={{
            background: 'radial-gradient(80% 100% at 50% 0%, rgb(44 111 101 / 0.16) 0%, transparent 70%)',
          }}
        />
      )}

      <header className="relative flex items-start justify-end gap-2">
        <div className="flex items-center gap-2">
          <DemandBadge demand={candidate.demand} />
          {candidate.is_featured && <FeaturedBadge />}

          {/* الشارة وتحتها عمرُها. لا رقم: الخادم لا يرسل النسبة (0–100) أبداً
              — «87/100» يوهم بدقّةٍ لا وجود لها، ويصِم صاحبه برقم. */}
          <span className="flex flex-col items-center gap-0.5">
            <StatusBadge status={candidate.employment_status} stale={candidate.status_stale} />
            <StatusAge days={candidate.status_age_days} stale={candidate.status_stale} />
          </span>
        </div>
      </header>

      <div className="relative flex items-center gap-3.5">
        <MaskedAvatar initials={candidate.initials} size={58} />

        <div className="min-w-0">
          {/* الاسم يظهر فقط حين يصل فعلاً من الخادم. */}
          {revealed && candidate.name !== undefined ? (
            <div
              className="truncate text-[15px] font-semibold text-ink"
              style={{ animation: 'kf-rise .5s ease both' }}
            >
              {candidate.name}
            </div>
          ) : (
            <div className="text-[15px] font-semibold text-[var(--k-text-muted)]">هوية محجوبة</div>
          )}

          <Link
            to={`/candidates/${candidate.ref}`}
            className="mt-0.5 block truncate text-[13px] font-medium text-primary hover:underline"
          >
            {candidate.title}
          </Link>
        </div>
      </div>

      <div className="relative flex flex-wrap gap-x-3.5 gap-y-2 text-xs text-[var(--k-text-secondary)]">
        <span className="inline-flex items-center gap-1.5">
          <BriefcaseIcon />
          {formatYears(candidate.years_experience)}
        </span>
        {candidate.city != null && (
          <span className="inline-flex items-center gap-1.5">
            <PinIcon />
            {candidate.city}
          </span>
        )}

        {/* الجنسية — صفةٌ مهنية تُصفّى بها الشركة بحثها (تأشيرة، توطين).
            `!= null` لا `!== undefined`: العلاقة محمَّلة دائماً، فيصل المفتاح
            بقيمة `null` لمن لا جنسيةَ في ملفّه — وفحصُ `undefined` وحده كان
            يُمرّره ثم ينهار على `.label`، فيُسقط صفحة التصفّح كلّها. */}
        {candidate.nationality != null && (
          <span className="inline-flex items-center gap-1.5">
            <GlobeIcon />
            {candidate.nationality.label}
          </span>
        )}

        <ListedSince days={candidate.listed_since_days} />
      </div>

      {candidate.skills !== undefined && candidate.skills.length > 0 && (
        <div className="relative flex flex-wrap gap-1.5">
          {candidate.skills.map((skill) => (
            <SkillChip key={skill}>{skill}</SkillChip>
          ))}
        </div>
      )}

      {/* فاصلٌ يخفت عند طرفيه بدل الخطّ الحادّ الممتدّ — يفصل دون أن يقطع. */}
      <div
        className="relative h-px"
        style={{
          background:
            'linear-gradient(90deg, transparent, var(--k-divider) 15%, var(--k-divider) 85%, transparent)',
        }}
      />

      <footer className="relative flex items-center justify-between gap-2.5">
        <div className="flex flex-col">
          <span className="text-[10px] font-medium tracking-wide text-[var(--k-text-muted)]">
            الراتب المتوقّع
          </span>
          <span className="numeric text-[14px] font-bold text-ink">
            {formatSalary(candidate.expected_salary)}
          </span>
        </div>

        <RevealButton
          revealed={revealed}
          fee={candidate.reveal_fee}
          pending={pending}
          compact
          onReveal={() => onReveal(candidate.ref)}
        />
      </footer>

      {/* بطاقة التواصل — إطار ذهبي، ثمرة الفعل المدفوع. */}
      {revealed && (candidate.phone !== undefined || candidate.email !== undefined) && (
        <ContactCard phone={candidate.phone} email={candidate.email} />
      )}
    </TiltCard>
  )
}

function ContactCard({ phone, email }: { phone?: string; email?: string }) {
  return (
    <div
      className="relative flex flex-col gap-2 rounded-[16px] border p-3.5"
      style={{
        background: 'var(--k-gold-tint)',
        borderColor: 'rgb(180 134 59 / 0.35)',
        boxShadow: '0 8px 20px -10px rgb(180 134 59 / 0.4)',
        animation: 'kf-rise .5s var(--k-ease) both',
      }}
    >
      {email !== undefined && (
        <div className="flex items-center gap-2 text-[13px] font-medium text-ink">
          <MailIcon />
          <span dir="ltr" className="truncate">
            {email}
          </span>
        </div>
      )}
      {phone !== undefined && (
        <div className="flex items-center gap-2 text-[13px] font-medium text-ink">
          <PhoneIcon />
          <span dir="ltr">{phone}</span>
        </div>
      )}
    </div>
  )
}

function FeaturedBadge() {
  return (
    <span
      className="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[10px] font-medium"
      style={{
        color: '#96682A',
        background: 'rgb(180 134 59 / 0.12)',
        borderColor: 'rgb(180 134 59 / 0.4)',
      }}
    >
      <svg width="11" height="11" viewBox="0 0 24 24" fill="var(--k-gold)" aria-hidden>
        <path d="m12 2 2.6 6.3 6.8.5-5.2 4.4 1.7 6.6L12 16.9 6.1 20.3l1.7-6.6L2.6 9.3l6.8-.5Z" />
      </svg>
      كفاءة مميّزة
    </span>
  )
}

function BriefcaseIcon() {
  return (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <rect x="3" y="7" width="18" height="13" rx="2" />
      <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
    </svg>
  )
}

function PinIcon() {
  return (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11Z" />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  )
}

function GlobeIcon() {
  return (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
      <circle cx="12" cy="12" r="9" />
      <path d="M3 12h18M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18Z" />
    </svg>
  )
}

function MailIcon() {
  return (
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--k-gold)" strokeWidth="2" aria-hidden>
      <rect x="2" y="4" width="20" height="16" rx="2" />
      <path d="M22 6 12 13 2 6" />
    </svg>
  )
}

function PhoneIcon() {
  return (
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--k-gold)" strokeWidth="2" aria-hidden>
      <path d="M4 4h4l2 5-3 2a12 12 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 2 6a2 2 0 0 1 2-2Z" />
    </svg>
  )
}
