# 07 — عقد الـ API

> كل المسارات تحت `/api/v1/`. الاستجابات JSON. المصادقة عبر Sanctum (راجع ADR-002).

## 1. المصادقة

| الطريقة | المسار | الوصف |
|---|---|---|
| `POST` | `/auth/register/employee` | تسجيل موظّف. يُسند دور `employee` تلقائياً. |
| `POST` | `/auth/register/company` | تسجيل شركة. يُسند دور `company`. |
| `POST` | `/auth/login` | |
| `POST` | `/auth/logout` | |
| `GET` | `/me` | المستخدم + **قائمة الصلاحيات الفعّالة** |
| `POST` | `/auth/forgot-password` · `/auth/reset-password` | |
| `POST` | `/auth/email/verify/{id}/{hash}` | |

### `GET /me`

```json
{
  "user": {
    "id": "01J…",
    "name": "شركة الخليج للتقنية",
    "type": "company",
    "status": "active",
    "profile": { "ref": "…", "verification_status": "verified" }
  },
  "permissions": ["candidate.browse", "candidate.reveal", "candidate.contact"],
  "settings": { "reveal.fee": 75, "billing.currency": "QAR" }
}
```

`settings` هنا تحوي **الإعدادات العامّة فقط** (`is_public = true`).

## 2. التصفّح والكشف ★

### `GET /candidates`

تصفّح الكفاءات المحجوبة. يتطلّب `candidate.browse`.

**معاملات الاستعلام:**

| المعامل | النوع | ملاحظة |
|---|---|---|
| `q` | string | يبحث في **المسمّى والمهارات والمجال فقط** — لا يمسّ الاسم |
| `field` | string | مفتاح lookup |
| `city` | string | |
| `status` | string | `leaving` \| `open` \| `stable` |
| `min_experience` | int | |
| `skills[]` | string[] | |
| `sort` | enum | `recent` \| `experience` — **لا ترتيب بحقل محجوب** |
| `page`, `per_page` | int | |

**الاستجابة (شركة لم تدفع):**

```json
{
  "data": [
    {
      "ref": "K7M2XQ4P",
      "title": "مهندس برمجيات أول",
      "field": { "key": "it", "label": "تقنية المعلومات" },
      "years_experience": 8,
      "city": "الدوحة",
      "employment_status": {
        "key": "leaving",
        "label": "راغب في ترك عمله",
        "meta": { "bg": "#E7F3EA", "color": "#1E7A44", "dot": "#2FA35C" }
      },
      "skills": ["React", "Laravel", "AWS"],
      "expected_salary": { "min": 22000, "max": 28000, "currency": "QAR" },
      "is_featured": false,
      "is_revealed": false,
      "reveal_fee": 75
    }
  ],
  "meta": { "total": 124, "per_page": 20, "current_page": 1 }
}
```

> **لاحظ:** لا `name`، ولا `initials`، ولا `phone`، ولا `email`، ولا `avatar`. **المفاتيح غائبة تماماً، لا فارغة.** هذا هو العقد. اختبار `assertArrayNotHasKey` يحرسه.

### `GET /candidates/{ref}`

الملفّ المفصّل. نفس منطق الحجب. يضيف `summary` و`experiences` و`educations`.

- ملفّ الموظّف الحالي لدى الشركة الطالبة → **`404`** (لا `403`).
- `ref` غير موجود → `404`.

### `POST /candidates/{ref}/reveal` ★

يتطلّب `candidate.reveal`. **يتطلّب ترويسة `Idempotency-Key`.**

**الاستجابات:**

| الحالة | المعنى | الجسم |
|---|---|---|
| `200` | كشف موجود مسبقاً — لا خصم | الملفّ كاملاً |
| `201` | تمّ الكشف (اشتراك، خصم فوري) | الملفّ كاملاً |
| `202` | بانتظار الدفع | `{ "payment_url": "…", "reveal_id": "…" }` |
| `402` | رصيد الاشتراك نافد | `{ "error": "insufficient_credits" }` |
| `403` | لا يملك الصلاحية | |
| `404` | غير موجود **أو** صاحب العمل الحالي | |
| `409` | تعارض تزامن (طلبان معاً) | |
| `422` | `Idempotency-Key` مفقود | |

**الاستجابة بعد الكشف:**

```json
{
  "ref": "K7M2XQ4P",
  "name": "أحمد الكواري",
  "initials": "أ.ك",
  "phone": "+974 50 11 92 74",
  "email": "a.alkuwari@mail.qa",
  "current_employer": "شركة …",
  "attachments": [{ "uuid": "…", "type": "cv", "original_name": "cv.pdf" }],
  "is_revealed": true,
  "revealed_at": "2026-07-09T…"
}
```

### `GET /reveals`

كشوفات الشركة الطالبة، مع الحالة والمبلغ **المجمّد** وقت الكشف.

## 3. الملفّ المهني (الموظّف)

| الطريقة | المسار |
|---|---|
| `GET` · `PUT` | `/profile` |
| `POST` · `DELETE` | `/profile/attachments` · `/profile/attachments/{uuid}` |
| `POST` · `PUT` · `DELETE` | `/profile/experiences[/{id}]` |
| `POST` · `PUT` · `DELETE` | `/profile/educations[/{id}]` |
| `POST` | `/profile/publish` · `/profile/unpublish` |
| `GET` | `/profile/reveals` — من كشف ملفّي (إن كان الإعداد يسمح) |

## 4. المرفقات

`GET /attachments/{uuid}` — يمرّ بـ `AttachmentPolicy` ثم يبثّ الملفّ من القرص الخاص.

- صاحب الملفّ → مسموح.
- شركة بكشف `completed` → مسموح.
- غير ذلك → `403`.
- **لا رابط مباشر إلى التخزين إطلاقاً.**

## 5. الدفع

| الطريقة | المسار |
|---|---|
| `POST` | `/payments/webhook/{provider}` — **بلا مصادقة، بتحقّق توقيع** |
| `GET` | `/payments` — معاملات الشركة |
| `POST` | `/admin/payments/{id}/refund` — يتطلّب `payment.refund` |

**الـ webhook:** يتحقّق من التوقيع → يتأكّد أن `provider_ref` جديد (منع إعادة التشغيل) → معاملة واحدة تكتب `payment.paid` + `reveal.completed` + `audit_log`. أي فشل → إعادة محاولة عبر الطابور.

## 6. لوحة المشرف

| المسار | الصلاحية |
|---|---|
| `GET/POST/PUT/DELETE /admin/roles` | `rbac.manage` |
| `GET /admin/permissions` | `rbac.manage` |
| `PUT /admin/roles/{role}/permissions` | `rbac.manage` — **حفظ تفاضلي** |
| `PUT /admin/users/{user}/roles` | `rbac.manage` |
| `PUT /admin/users/{user}/permissions` | `rbac.manage` — استثناءات فردية |
| `GET /admin/settings` · `PUT /admin/settings` | `settings.view` / `settings.update` |
| `GET/POST/PUT/DELETE /admin/lookups` | `lookup.manage` |
| `GET /admin/users` | `user.manage` |
| `PUT /admin/companies/{id}/verify` | `company.approve` |
| `GET /admin/audit-logs` | `audit.view` |
| `GET /admin/reports/revenue` | `report.export` |

## 7. شكل الأخطاء الموحّد

```json
{
  "message": "رسالة مفهومة بالعربية",
  "error": "insufficient_credits",
  "errors": { "field": ["…"] }
}
```

| الرمز | متى |
|---|---|
| `401` | غير مصادَق |
| `402` | مطلوب دفع / رصيد نافد |
| `403` | مصادَق بلا صلاحية |
| `404` | غير موجود **أو مخفيّ عمداً** (عمى صاحب العمل) |
| `409` | تعارض تزامن |
| `422` | فشل تحقّق |
| `429` | تجاوز حدّ المعدّل |

> **لا تُميّز أبداً** بين «غير موجود» و«ممنوع رؤيته» في الملفّات. كلاهما `404`. التمييز تسريب.

## 8. تحديد المعدّل

| المسار | الحدّ |
|---|---|
| `/auth/login` | 5 / دقيقة / IP |
| `/candidates` (تصفّح) | 60 / دقيقة / مستخدم |
| `/candidates/{ref}` | 100 / دقيقة / مستخدم |
| `/candidates/{ref}/reveal` | 10 / دقيقة / مستخدم |

الغرض من حدّ التصفّح: منع بناء قاعدة منافسة بالكشط، حتى لو كانت محجوبة.

## 9. الإصدار والتوليد

- تُولَّد `openapi.json` من الكود.
- تُشتقّ أنواع TypeScript منها آلياً إلى `web/src/shared/api/types.ts`.
- **مصدر حقيقة واحد للعقد.** لا كتابة أنواع يدوياً في الواجهة.

---

*راجع: [`06-masking-and-reveal.md`](06-masking-and-reveal.md) لسبب كل قاعدة هنا.*
