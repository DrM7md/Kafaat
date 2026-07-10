/**
 * أنواع عقد الـ API — Brain/07-api-contract.md.
 *
 * ⚠️ لاحظ أن الحقول الحسّاسة اختيارية (`?`) لا `| null`.
 *    هذا ليس تفصيلاً نحوياً: الخادم **يحذف** المفتاح ولا يُرسله فارغاً (ADR-003).
 *    فيجبرك TypeScript على التحقّق من وجوده قبل قراءته — وهو ما نريد.
 */

export type Permission =
  | 'profile.view' | 'profile.create' | 'profile.update' | 'profile.moderate'
  | 'profile.publish' | 'profile.approve'
  | 'candidate.browse' | 'candidate.reveal' | 'candidate.contact' | 'candidate.grant_reveal'
  | 'company.view' | 'company.approve' | 'company.suspend'
  | 'payment.view' | 'payment.refund' | 'subscription.manage'
  | 'settings.view' | 'settings.update' | 'lookup.manage' | 'rbac.manage'
  | 'audit.view' | 'user.manage' | 'report.export'

export type UserType = 'employee' | 'company' | 'admin'

export type Lookup = {
  id: number
  key: string
  label: string
  meta?: { bg: string; color: string; dot: string } | null
}

export type SalaryRange = {
  min: number | null
  max: number | null
  currency: string
}

/** بطاقة الكفاءة كما تصل من الـ API — محجوبةً أو مكشوفة. */
export type Candidate = {
  ref: string
  title: string
  field?: Lookup
  years_experience: number
  city?: string | null
  employment_status?: Lookup
  skills?: string[]
  expected_salary: SalaryRange | null
  is_featured: boolean
  is_revealed: boolean
  reveal_fee: number

  // ── تصل فقط بعد كشفٍ صالح. غائبة تماماً قبله. ──
  name?: string
  initials?: string
  phone?: string
  email?: string
  current_employer?: string | null
  attachments?: Attachment[]

  // ── تفصيلية ──
  summary?: string | null
  experiences?: Experience[]
  educations?: Education[]
}

export type Experience = {
  title: string
  is_current: boolean
  started_on: string
  ended_on: string | null
  description: string | null
  /** غائب لجهة العمل الحالية قبل الكشف. */
  company_name?: string
}

export type Education = {
  institution: string
  degree: string
  field_of_study: string | null
  graduated_year: number | null
}

export type Attachment = {
  uuid: string
  type: 'cv' | 'certificate' | 'good_conduct' | 'logo'
  original_name: string
  mime: string
  size: number
  url: string
}

export type Me = {
  user: {
    id: number
    name: string
    email: string
    phone: string | null
    type: UserType
    status: string
    profile: Record<string, unknown> | null
  }
  permissions: Permission[]
  settings: Record<string, unknown>
}

export type Paginated<T> = {
  data: T[]
  meta: { total: number; current_page: number; last_page: number; per_page: number }
}
