# 03 — نموذج البيانات

## 1. المخطّط العامّ (ERD)

```
                        ┌──────────┐
                        │  users   │  type: employee | company | admin
                        └────┬─────┘
             ┌───────────────┼───────────────┐
             │               │               │
   ┌─────────▼────────┐  ┌───▼──────────┐  ┌─▼──────────┐
   │ employee_profiles│  │company_prof. │  │  (admin)   │
   └─────────┬────────┘  └───┬──────────┘  └────────────┘
             │               │
   ┌─────────┼─────────┐     │
   │         │         │     │
┌──▼───┐ ┌───▼────┐ ┌──▼──────────┐
│skills│ │experi- │ │ attachments │ (morph)
│(pivot)│ │ences  │ │  cv/cert    │
└──────┘ └────────┘ └─────────────┘
             │               │
             └───────┬───────┘
                     │
          ┌──────────▼───────────┐        ┌──────────┐
          │  candidate_reveals   │───────►│ payments │
          │ (company ↔ employee) │        └──────────┘
          └──────────────────────┘

   ┌──────────┐  ┌──────────┐  ┌─────────────┐  ┌───────────────┐
   │ settings │  │ lookups  │  │ audit_logs  │  │  consents     │
   └──────────┘  └──────────┘  └─────────────┘  └───────────────┘

   جداول Spatie: roles · permissions · model_has_roles ·
                 role_has_permissions · model_has_permissions
```

## 2. الجداول

### `users`

| العمود | النوع | ملاحظة |
|---|---|---|
| `id` | bigint PK | **لا يُعرض في أي مسار عامّ** |
| `name` | string | **حقل محجوب** — لا يغادر الخادم دون كشف |
| `email` | string unique | محجوب |
| `phone` | string nullable | محجوب |
| `password` | string | |
| `type` | enum(`employee`,`company`,`admin`) | نوع الحساب، لا الدور |
| `status` | enum(`active`,`suspended`,`pending`) | |
| `email_verified_at`, `last_login_at` | timestamp | |
| `locale` | string(5) default `ar` | |

> **`type` ليس دوراً.** هو نوع الحساب البنيوي (أي جدول ملفّ يرتبط به). الصلاحيات كلّها عبر Spatie. ممنوع `if ($user->type === 'admin')`.

### `employee_profiles`

| العمود | النوع | محجوب؟ |
|---|---|---|
| `id` | bigint PK | داخلي |
| `user_id` | FK unique | |
| `ref` | char(8) unique index | ❌ **المعرّف العامّ** — عشوائي غير متسلسل |
| `title` | string | ❌ المسمّى الوظيفي |
| `field_id` | FK lookups | ❌ المجال |
| `years_experience` | tinyint | ❌ |
| `city_id` | FK lookups | ❌ |
| `employment_status_id` | FK lookups | ❌ |
| `expected_salary_min` / `_max` | int nullable | ❌ |
| `currency` | char(3) default `QAR` | ❌ |
| `summary` | text | ❌ نبذة (تُفحص من ذكر الاسم) |
| `notice_period_clause` | text nullable | ✅ البند الجزائي / شرط الإنهاء |
| `penalty_amount` | int nullable | ✅ |
| `current_employer_company_id` | FK companies nullable | ✅ **مفتاح قاعدة عمى صاحب العمل** |
| `current_employer_name` | string nullable | ✅ لمن لا يوجد في المنصّة |
| `visibility` | enum(`public`,`hidden`) | |
| `is_featured` | bool | ❌ شارة «كفاءة مميّزة» |
| `completeness` | tinyint (0-100) | |
| `published_at` | timestamp nullable | |

**فهارس:** `(field_id, years_experience)` · `(city_id)` · `(employment_status_id)` · `(visibility, published_at)` · `ref` unique.

> `current_employer_company_id` هو ما يجعل قاعدة عمى صاحب العمل قابلة للتنفيذ. لكنه **يعتمد على صدق الموظّف** في إدخاله. المخاطرة مقبولة في MVP، وتُوثَّق للمستخدم صراحةً: «أدخل جهة عملك الحالية لنمنعها من رؤية ملفّك».

### `company_profiles`

| العمود | النوع |
|---|---|
| `id`, `user_id` FK unique | |
| `legal_name`, `brand_name` | string |
| `cr_number` | string nullable — السجلّ التجاري |
| `verification_status` | enum(`unverified`,`pending`,`verified`,`rejected`) |
| `logo_path` | string nullable |
| `size`, `industry_id` | FK lookups |

### `skills` + `employee_profile_skill`

`skills(id, name unique, slug)` — قائمة عامّة يديرها المشرف.
الجدول الوسيط `(employee_profile_id, skill_id)` بمفتاح أساسي مركّب.

### `experiences` · `educations`

مرتبطان بـ `employee_profile_id`. حقل `company_name` في `experiences` **محجوب للخبرة الحالية فقط** (`is_current = true`)، ومكشوف للخبرات السابقة — لأن الماضي لا يعرّض الموظّف لخطر.

### `attachments` (متعدّد الأشكال)

| العمود | النوع |
|---|---|
| `attachable_type`, `attachable_id` | morph |
| `type` | enum(`cv`,`certificate`,`good_conduct`,`logo`) |
| `disk`, `path` | string — **قرص خاص، ليس `public`** |
| `original_name`, `mime`, `size` | |
| `is_private` | bool default true |

**الوصول حصراً** عبر `GET /api/v1/attachments/{uuid}` الذي يمرّ بـ `AttachmentPolicy` ثم يبثّ الملفّ. لا رابط مباشر أبداً.

### `candidate_reveals` ★

| العمود | النوع | ملاحظة |
|---|---|---|
| `id` | bigint PK | |
| `company_profile_id` | FK | |
| `employee_profile_id` | FK | |
| `payment_id` | FK nullable | فارغ في نموذج الاشتراك |
| `source` | enum(`payment`,`subscription`,`granted`) | `granted` = منحة من المشرف |
| `amount`, `currency` | int, char(3) | **يُجمَّد سعر وقت الكشف** لا يُقرأ من الإعدادات لاحقاً |
| `status` | enum(`pending`,`completed`,`failed`,`refunded`) | **`completed` وحدها تفكّ الحجب** |
| `idempotency_key` | string unique nullable | |
| `revealed_at`, `refunded_at` | timestamp nullable | |
| `expires_at` | timestamp nullable | لدعم كشفٍ مؤقّت مستقبلاً |

**الفهارس:**
- `unique(company_profile_id, employee_profile_id, status)` — يمنع كشفين `completed` للزوج نفسه، ويسمح بكشفٍ جديد بعد `refunded`.
- `index(company_profile_id, status)` — لقائمة «كشوفاتي».

> **تجميد السعر إلزامي.** لو قرأنا الرسم من الإعدادات وقت العرض، لتغيّرت الفواتير أثراً رجعياً عند تعديل المشرف للسعر.

### `payments`

| العمود | النوع |
|---|---|
| `company_profile_id` | FK |
| `provider` | string — `qpay` \| `dibsy` \| `stripe`… |
| `provider_ref` | string unique — يمنع إعادة تشغيل الـ webhook |
| `idempotency_key` | string unique |
| `amount`, `currency`, `fee` | |
| `status` | enum(`pending`,`paid`,`failed`,`refunded`) |
| `paid_at`, `refunded_at` | |
| `payload` | json — استجابة البوّابة الخام |

### `settings`

| العمود | النوع |
|---|---|
| `key` | string PK — `reveal.fee`, `masking.fields`… |
| `value` | json |
| `type` | enum(`string`,`int`,`bool`,`json`,`enum`,`array`) |
| `group` | string — `billing`, `masking`, `privacy`, `features` |
| `options` | json nullable — للقيم المحصورة |
| `is_public` | bool — أيُرسَل لغير المصادَقين؟ |

راجع الكتالوج الكامل في [`05-settings-engine.md`](05-settings-engine.md).

### `lookups`

| العمود | النوع |
|---|---|
| `group` | string — `employment_status`, `field`, `city`, `industry` |
| `key` | string — `leaving`, `open`, `stable` |
| `label_ar`, `label_en` | string |
| `meta` | json — **ألوان الشارة هنا** `{bg, color, dot}` |
| `sort_order`, `is_active` | |

`unique(group, key)`.

> وضع ألوان الشارات في `meta` هو ما يجعل «إضافة حالة وظيفية رابعة بلونها» عملاً إدارياً لا برمجياً — وهو صميم المبدأ الثاني.

### `audit_logs`

| العمود | النوع |
|---|---|
| `actor_id` | FK users nullable |
| `action` | string — `candidate.revealed`, `settings.updated`, `role.assigned` |
| `subject_type`, `subject_id` | morph |
| `ip`, `user_agent` | |
| `meta` | json — القيم قبل/بعد للتعديلات |
| `created_at` | index |

**لا حذف ولا تعديل.** جدول إلحاقيّ فقط (append-only).

### `consents` — قانون رقم 13

| العمود | النوع |
|---|---|
| `user_id` | FK |
| `type` | enum(`terms`,`privacy`,`sensitive_data`,`marketing`) |
| `version` | string — نسخة الوثيقة الموافَق عليها |
| `granted_at`, `revoked_at` | |
| `ip`, `user_agent` | |

راجع [`09-legal-compliance.md`](09-legal-compliance.md).

### مؤجّلة (خارج MVP)

`subscription_plans` · `subscriptions` · `reveal_credits` · `notifications` (جدول Laravel القياسي).

## 3. قواعد النزاهة (Integrity)

1. `candidate_reveals.status = completed` **لا يُكتب** إلّا داخل معاملة تشمل `payments.status = paid` (أو خصم رصيد).
2. حذف مستخدم → **حذف ناعم** (soft delete) لا صلب، حفاظاً على نزاهة الفواتير وسجلّ التدقيق.
3. طلب الحذف الكامل (حقّ قانوني، م8) → **إخفاء هوية (anonymization)** لا `DELETE`: تُصفَّر الحقول المعرِّفة ويبقى الصفّ المالي.
4. `audit_logs` لا تُحذف أبداً. أرشفة، لا حذف.
5. كل مفاتيح خارجية بـ `restrictOnDelete` افتراضياً — لا `cascade` صامت على أي شيء مالي.

## 4. البذور (Seeders)

| Seeder | المحتوى |
|---|---|
| `PermissionSeeder` | كتالوج الصلاحيات ([`04-rbac.md`](04-rbac.md)) — **قابل لإعادة التشغيل (idempotent)** |
| `RoleSeeder` | الأدوار الافتراضية |
| `SettingSeeder` | الإعدادات الافتراضية ([`05-settings-engine.md`](05-settings-engine.md)) |
| `LookupSeeder` | الحالات الثلاث بألوانها، المجالات، المدن القطرية |
| `DemoSeeder` | بيانات التصميم الأربعة (أحمد الكواري، نورة السليطي، راشد المناعي، ليلى الأنصاري) — **بيئة التطوير فقط** |

---

*راجع: [`06-masking-and-reveal.md`](06-masking-and-reveal.md) لكيفية استهلاك هذه الجداول.*
