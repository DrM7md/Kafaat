# 05 — محرّك الإعدادات الديناميكي

## 1. المبدأ

> **كل قيمةٍ قد نرغب في تغييرها مستقبلاً تعيش في الإعدادات، لا في الكود.**

اختبار بسيط قبل كتابة أي قيمة ثابتة: *«لو طلب المالك تغيير هذا غداً، أأحتاج إلى نشر كود؟»* إن كان الجواب نعم — فهو إعداد.

```php
❌ $fee = 75;
❌ const MASKED_FIELDS = ['name', 'phone'];
✅ settings('reveal.fee');
✅ settings('masking.fields');
```

## 2. البنية

جدول `settings` **مُهيّأ الأنواع (typed)** ومُصنَّف. راجع [`03-data-model.md`](03-data-model.md).

### `SettingsRepository`

```php
interface SettingsRepository
{
    public function get(string $key, mixed $default = null): mixed;  // من الكاش
    public function set(string $key, mixed $value): void;            // + إبطال الكاش + تدقيق
    public function group(string $group): array;
    public function all(bool $publicOnly = false): array;
}
```

**قواعد ملزمة:**
- القراءة من الكاش دائماً (`Cache::rememberForever`)، لا استعلام في كل طلب.
- `set()` يُبطل الكاش **ويكتب `audit_logs`** بالقيمة قبل وبعد.
- كل قيمة تُصبّ (cast) بحسب `type` — لا `"true"` نصّية تُعامل كصحيحة.
- التحقّق من `options` عند القيم المحصورة قبل الحفظ.
- الإعدادات ذات `is_public = false` **لا تُرسَل للواجهة أبداً**.

### دالّة مساعدة

```php
function settings(string $key, mixed $default = null): mixed
{
    return app(SettingsRepository::class)->get($key, $default);
}
```

## 3. كتالوج الإعدادات

### `billing` — نموذج الربح

| المفتاح | النوع | الافتراضي | الوصف |
|---|---|---|---|
| `billing.model` | enum | `pay_per_reveal` | `pay_per_reveal` \| `subscription` \| `hybrid` |
| `billing.currency` | string | `QAR` | |
| `reveal.fee` | int | `75` | رسم الكشف الواحد (من التصميم) |
| `billing.gateway` | enum | `null` | البوّابة المفعّلة — ⬜ معلّق |
| `billing.tax_percentage` | int | `0` | |

### `masking` — حجب الحقول ★

| المفتاح | النوع | الافتراضي |
|---|---|---|
| `masking.fields` | array | `["name","initials","avatar","phone","email","current_employer","attachments","links"]` |
| `masking.mask_current_experience_employer` | bool | `true` |
| `masking.show_redacted_cv` | bool | `false` (مؤجّل — راجع [`06`](06-masking-and-reveal.md) §3) |

> هذا المفتاح هو **تجسيد المبدأ الثاني كلّه**. يستطيع المشرف مستقبلاً كشف «جهة العمل الحالية» أو حجب «الراتب المتوقّع» بنقرة، دون سطر برمجي.
>
> **حارس ملزم:** لا يجوز إزالة `name` أو `phone` أو `email` من `masking.fields` دون تأكيد مزدوج في اللوحة — إزالتها تعني منح المنتج مجّاناً. تحذير صريح: «هذا يجعل بيانات التواصل مرئيةً للجميع بلا دفع».

### `refund` — سياسة الاسترداد

⬜ القيم معلّقة على قرارك (القرار #2).

| المفتاح | النوع | مقترح |
|---|---|---|
| `refund.enabled` | bool | `true` |
| `refund.window_days` | int | `7` |
| `refund.percentage` | int | `100` |
| `refund.trigger` | enum | `employee_declined` \| `no_response` \| `manual_only` |
| `refund.reblocks_access` | bool | `true` — يُعاد الحجب بعد الاسترداد |

### `privacy` — قواعد الخصوصية

| المفتاح | النوع | الافتراضي |
|---|---|---|
| `privacy.employer_blind` | bool | `true` |
| `privacy.notify_on_reveal` | bool | `true` |
| `privacy.allow_employee_blocklist` | bool | `false` (مؤجّل) |
| `privacy.profile_visibility_default` | enum | `public` |

### `features` — مفاتيح الميزات

| المفتاح | الافتراضي | الأثر |
|---|---|---|
| `features.subscriptions` | `false` | يُظهر باقات الاشتراك |
| `features.company_verification` | `false` | يفعّل مسار التوثيق |
| `features.notifications` | `true` | |
| `features.good_conduct_upload` | `true` | رفع شهادة حُسن السيرة — راجع [`09`](09-legal-compliance.md) |
| `features.matching_score` | `false` | نسبة التوافق (رقم ثابت في التصميم `1c`) |

### `platform`

| المفتاح | الافتراضي |
|---|---|
| `platform.name` | `كفاءات` |
| `platform.support_email` | — |
| `platform.terms_version` | `1.0` |
| `platform.privacy_version` | `1.0` |

## 4. القوائم المرجعية (Lookups)

منفصلة عن `settings` لأنها **صفوف متعدّدة مرتّبة**، لا قيماً مفردة.

| المجموعة | المحتوى الافتراضي |
|---|---|
| `employment_status` | `leaving`, `open`, `stable` — **بألوانها في `meta`** |
| `field` | تقنية المعلومات، التسويق، المالية، الموارد البشرية، … |
| `city` | الدوحة، الوكرة، الريّان، الخور، … |
| `industry` | لتصنيف الشركات |
| `company_size` | |

### `meta` للحالة الوظيفية — نقلاً حرفياً عن التصميم

```json
{
  "leaving": { "bg": "#E7F3EA", "color": "#1E7A44", "dot": "#2FA35C" },
  "open":    { "bg": "#FBF0D9", "color": "#96682A", "dot": "#C9922F" },
  "stable":  { "bg": "#ECEFEE", "color": "#566460", "dot": "#8A9793" }
}
```

الواجهة تقرأ اللون من الـ API لا من ملفّ CSS. هكذا تصبح إضافة حالةٍ رابعة عملاً إدارياً.

## 5. لوحة الإعدادات

**متطلّبات وظيفية:**
- تجميع بحسب `group` في تبويبات.
- نوع الحقل يُشتقّ من `type` (مفتاح تبديل للـ `bool`، قائمة للـ `enum`، محدّد متعدّد للـ `array`).
- تحذير أحمر صريح على إعدادات `masking.*` و`billing.*`.
- معاينة الأثر قبل الحفظ حيثما أمكن.
- كل حفظ ← صفّ `audit_logs` بالقيم قبل/بعد.

⬜ **الشاشة غير متوفّرة في `UI/` بعد.**

## 6. الاختبارات الملزمة

1. `settings('reveal.fee')` يُعيد `int` لا `string`.
2. تعديل `masking.fields` من الـ API ← الاستجابة التالية للتصفّح تعكسه **فوراً**.
3. `set()` يكتب `audit_logs` بالقيمة قبل وبعد.
4. إعداد `is_public = false` لا يظهر في `GET /settings/public`.
5. قيمة خارج `options` ← `422`.
6. إزالة `name` من `masking.fields` دون علم التأكيد ← `422`.
7. الكاش يُبطَل عند `set()` (استعلام واحد فقط بعد التعديل).

---

*راجع: [`06-masking-and-reveal.md`](06-masking-and-reveal.md) — أكبر مستهلك لهذا المحرّك.*
