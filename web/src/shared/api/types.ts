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
  | 'job.manage' | 'job.browse' | 'job.interest' | 'job.grant_slots'
  | 'company.view' | 'company.approve' | 'company.suspend'
  | 'payment.view' | 'payment.refund' | 'subscription.manage'
  | 'settings.view' | 'settings.update' | 'lookup.manage' | 'rbac.manage'
  | 'audit.view' | 'user.manage' | 'report.export'

/** وظيفة تنشرها شركة — «مطلوب مهندس كهربائي». */
export type Job = {
  ref: string
  title: string
  description: string
  company?: { legal_name: string; brand_name: string | null }

  /**
   * ⚠️ `| null` لا `?` وحدها.
   *
   * العلاقة **محمَّلة دائماً** (`with(['field'])`)، فيصل المفتاح — لكن قيمته
   * `null` حين لا مجالَ للوظيفة. نوعٌ يقول `?Lookup` وحدها يجعل TypeScript
   * يطمئنّ إلى أنّ فحص `!== undefined` كافٍ، فينهار العرض على `.label`.
   */
  field?: Lookup | null
  city?: string | null
  min_experience: number
  salary: SalaryRange | null
  status: 'draft' | 'open' | 'closed'
  published_at: string | null

  /** هل أبديتُ إعجابي بها؟ (للموظّف) */
  has_interest?: boolean

  // ── لصاحب الوظيفة وحده ──
  is_paid_slot?: boolean
  closed_at?: string | null
  interests_count?: number
  new_interests_count?: number
}

/** حصّة الوظائف المفتوحة — يضبطها المشرف من الإعدادات. */
export type JobSlots = {
  free: number
  purchased: number
  credit: number
  total: number
  used: number
  remaining: number
  extra_fee: number
}

export type UserType = 'employee' | 'company' | 'admin'

export type Lookup = {
  id: number
  key: string
  label: string

  /** المدينة تعرف دولتها. تُرشَّح المدن بالدولة المختارة. */
  parent_id?: number | null

  /**
   * حمّالة أوجه بحسب المجموعة: ألوان الشارة للحالة الوظيفية، والعملة
   * للدولة. كلاهما من قاعدة البيانات لا من الكود.
   */
  meta?: (Partial<StatusMeta> & Partial<CountryMeta>) | null

  status?: 'approved' | 'pending' | 'rejected'
}

/**
 * ألوان الشارة — وعتبتها على مؤشّر الانتقال.
 *
 * `readiness_min` هي ما يجعل «إضافة حالةٍ خامسة عند 85 بلونٍ أحمر» عملاً
 * إدارياً: الواجهة لا تعرف أسماء الحالات ولا عتباتها، تقرؤها من القائمة.
 * راجع `statusForReadiness` في shared/lib/readiness.
 */
export type StatusMeta = { bg: string; color: string; dot: string; readiness_min: number }

/** عملة الدولة — `formatMoney` يقرأ منها الرمز. لا «ر.ق» في الكود. */
export type CountryMeta = { currency: string; currency_label: string; dial_code: string }

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

  /**
   * الجنسية — من هو، لا أين يعمل. تُصفّى بها الشركة بحثها.
   *
   * ⚠️ `| null`: العلاقة محمَّلة دائماً فيصل المفتاح، وقيمتُه `null` لمن لا
   *    جنسيةَ في ملفّه. فحصُ `!== undefined` وحده يُمرّره ثمّ ينهار على `.label`.
   */
  nationality?: Lookup | null

  employment_status?: Lookup

  /**
   * ★ عمر الحالة بالأيام — لا النسبة التي اشتُقّت منها.
   *
   * الخادم **لا يرسل `readiness` (0–100) أبداً**: رقمٌ كهذا يوهم بدقّةٍ لا
   * وجود لها. لكنّ عمرَه يُرسَل، لأنّه ما يفرّق الخبر من الذكرى — والشركة
   * تدفع رسم الكشف على أساس هذه الشارة.
   *
   * `null` لمن لم يضبط مؤشّره قطّ. و`status_stale` تعني: بارت، فلا تعرضها
   * كأنّها اليوم.
   */
  status_age_days?: number | null
  status_stale?: boolean

  skills?: string[]
  expected_salary: SalaryRange | null
  is_featured: boolean
  is_revealed: boolean

  /** سعر كشف هذا الملفّ — من سعر مجاله، لا من إعدادٍ واحد للجميع. */
  reveal_fee: number

  /** منذ كم يوم وهو معروضٌ للشركات (من تاريخ اعتماد ملفّه). */
  listed_since_days?: number | null

  /**
   * مستوى الطلب — شارةٌ لا رقم.
   *
   * الخادم لا يرسل عدد الكشوف أبداً: إرساله يُخبر الشركةَ بنشاط منافسيها،
   * ويصِم من لم يُطلَب بعدُ بـ «0». `null` تعني «لا شارة».
   */
  demand?: 'rising' | 'high' | null

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

export type VerificationStatus = 'unverified' | 'pending' | 'verified' | 'rejected'

/** ملفّ الشركة كما يصل في `/me` — لا يغادر صاحبَه، فيصل كاملاً. */
export type CompanyAccount = {
  id: number
  legal_name: string
  brand_name: string | null

  /** إجباريّ: به يُوثَّق وجود الشركة. تبديله يُسقط التوثيق. */
  cr_number: string | null

  industry_id: number | null
  size_id: number | null
  verification_status: VerificationStatus
}

export type EmployeeAccount = {
  ref: string
  title: string
  is_published: boolean
  completeness: number
}

export type Me = {
  user: {
    id: number
    name: string
    email: string
    phone: string | null
    type: UserType
    status: string

    /** `type` يُميّز أيّهما: حساب الشركة ملفُّها، وحساب الموظّف موجزُ ملفّه. */
    profile: EmployeeAccount | CompanyAccount | null
  }
  permissions: Permission[]
  settings: Record<string, unknown>
}

/** حارس نوع: `profile` ملفُّ شركة؟ */
export function isCompanyAccount(
  profile: Me['user']['profile'],
  type: UserType,
): profile is CompanyAccount {
  return type === 'company' && profile !== null && 'legal_name' in profile
}

export type Paginated<T> = {
  data: T[]
  meta: { total: number; current_page: number; last_page: number; per_page: number }
}
