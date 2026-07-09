# 04 — نظام الصلاحيات المرن (RBAC Engine)

## 1. المبدأ

> **لا أدوار مثبّتة في الكود.** كل فحصٍ يسأل عن **صلاحية**، لا عن دور.

```php
✅ $user->can('candidate.reveal')
❌ $user->type === 'admin'
❌ $user->hasRole('super-admin')      // حتى هذا ممنوع في منطق العمل
```

الاستثناء الوحيد المسموح: `Gate::before()` يمنح المشرف العام كل شيء (§5).

## 2. المفاهيم الثلاثة

| المفهوم | التعريف | مثال |
|---|---|---|
| **الصلاحية** (Permission) | أصغر وحدة تحكّم. اسمها `مجموعة.فعل`. | `candidate.reveal` |
| **الدور** (Role) | حزمة صلاحيات. يُنشئه المشرف. | «مشرف محتوى» |
| **الإسناد** (Assignment) | ربط مستخدم بدور أو أكثر، مع استثناءات فردية. | أحمد = مشرف محتوى + `payment.refund` مباشرة |

الأساس التقني: `spatie/laravel-permission` — لأنها تدعم أدواراً متعدّدة لكل مستخدم، و**صلاحيات مباشرة على مستوى المستخدم** تتجاوز الدور (وهو ما يحقّق «الاستثناءات الفردية» المطلوبة).

## 3. كتالوج الصلاحيات المقترح

⚠️ **معلّق على إقرارك** (القرار #4 في [`00-vision-and-scope.md`](00-vision-and-scope.md)).

| المجموعة | الصلاحية | المعنى |
|---|---|---|
| **الملفّات** | `profile.view` | عرض ملفّ مهني |
| | `profile.create` | إنشاء ملفّ |
| | `profile.update` | تعديل ملفّه هو |
| | `profile.moderate` | **رؤية كل الملفّات مكشوفة** + تعليقها |
| | `profile.publish` | نشر/سحب ملفّ |
| **الكشف** | `candidate.browse` | تصفّح قاعدة الكفاءات |
| | `candidate.reveal` | تنفيذ عملية كشف |
| | `candidate.contact` | رؤية بيانات التواصل بعد الكشف |
| | `candidate.grant_reveal` | منح كشفٍ مجّاني (منحة إدارية) |
| **الشركات** | `company.view` | عرض ملفّات الشركات |
| | `company.approve` | توثيق شركة |
| | `company.suspend` | تعليق شركة |
| **المدفوعات** | `payment.view` | الاطّلاع على المعاملات |
| | `payment.refund` | تنفيذ استرداد |
| | `subscription.manage` | إدارة الاشتراكات والباقات |
| **الإعدادات** | `settings.view` | عرض الإعدادات |
| | `settings.update` | تعديل الإعدادات |
| | `lookup.manage` | إدارة القوائم المرجعية |
| | `rbac.manage` | **إدارة الأدوار والصلاحيات** |
| **النظام** | `audit.view` | الاطّلاع على سجلّ التدقيق |
| | `user.manage` | إدارة المستخدمين |
| | `report.export` | تصدير التقارير |

> **`profile.moderate` هي أخطر صلاحية في المنصّة.** من يملكها يرى كل الأسماء وبيانات التواصل مجّاناً — أي يرى المنتج كلّه بلا دفع. تُمنح بحذر شديد، وكل استخدامٍ لها **يُسجَّل في `audit_logs`** بفعل `profile.viewed_as_moderator`. راجع §6.

### حسّاسية الصلاحيات

| المستوى | الصلاحيات | القاعدة |
|---|---|---|
| 🔴 حرجة | `profile.moderate`, `rbac.manage`, `settings.update`, `payment.refund`, `candidate.grant_reveal` | تُسجَّل كل ممارسة لها. لا تُمنح لدورٍ تلقائياً. |
| 🟡 حسّاسة | `audit.view`, `user.manage`, `company.suspend`, `report.export` | تُسجَّل. |
| 🟢 عادية | الباقي | |

## 4. الأدوار الافتراضية (بذور)

الأدوار **بذورٌ أولية** لا حقائق ثابتة — يعدّلها المشرف كما يشاء.

| الدور | الصلاحيات |
|---|---|
| `super-admin` | الكل (عبر `Gate::before`) |
| `employee` | `profile.view`, `profile.create`, `profile.update`, `profile.publish` |
| `company` | `candidate.browse`, `candidate.reveal`, `candidate.contact`, `profile.view` |
| `content-moderator` | `profile.view`, `profile.moderate`, `company.view` |
| `support` | `profile.view`, `company.view`, `payment.view`, `audit.view` |

> الدوران `employee` و`company` **يُسنَدان تلقائياً عند التسجيل** بحسب `users.type`. هذا هو الجسر الوحيد بين نوع الحساب والصلاحيات، ويقع مرّة واحدة في `RegisterAction` — لا في منطق العمل.

## 5. آلية الفرض

### في الخلفية — ثلاث طبقات متتالية

```php
// 1) Middleware على المسار — الحارس الخشن
Route::post('/candidates/{profile:ref}/reveal', RevealController::class)
     ->middleware(['auth:sanctum', 'can:candidate.reveal']);

// 2) Policy — الحارس الدقيق (يعرف السياق: من، وأي كائن)
public function reveal(User $user, EmployeeProfile $profile): Response
{
    if ($this->isCurrentEmployer($user, $profile)) {
        return Response::denyAsNotFound();     // 404 لا 403 — راجع 06
    }
    return $user->can('candidate.reveal')
        ? Response::allow()
        : Response::deny();
}

// 3) Resource — حارس الحقول
// Policy تقرّر «هل يفتح الملفّ؟» · Resource يقرّر «أي حقولٍ يرى؟»
```

**الطبقات الثلاث معاً، لا واحدة منها.** غياب أي طبقة ثغرة.

### المشرف العام

```php
// AuthServiceProvider
Gate::before(fn (User $user) => $user->hasRole('super-admin') ? true : null);
```

هذا **الموضع الوحيد** في المشروع كلّه الذي يُذكر فيه اسم دور.

### في الواجهة — للعرض فقط

عند `GET /me` يُعيد الـ API **قائمة الصلاحيات الفعّالة** (الدور + الاستثناءات الفردية، مدموجة ومحسوبة في الخادم):

```json
{
  "user": { "id": "…", "name": "…", "type": "company" },
  "permissions": ["candidate.browse", "candidate.reveal", "candidate.contact"]
}
```

فتستخدمها React:

```tsx
<Can permission="candidate.reveal">
  <RevealButton fee={fee} />
</Can>
```

> **تذكير:** `<Can>` يخفي زرّاً. **لا يحمي شيئاً.** لو حذفه المستخدم من DOM ونادى الـ API، فالـ Policy هي من تردّه. الواجهة تجميل، والخلفية قانون.

## 6. التدقيق الإلزامي

كل ممارسة لصلاحية 🔴 حرجة تكتب صفّاً في `audit_logs`:

| الفعل | `action` |
|---|---|
| مشرف رأى ملفّاً مكشوفاً بصلاحية الإشراف | `profile.viewed_as_moderator` |
| تعديل صلاحيات دور | `role.permissions_updated` (مع القيم قبل/بعد في `meta`) |
| إسناد/نزع دور | `role.assigned` / `role.revoked` |
| منح/منع صلاحية فردية | `permission.granted_direct` / `permission.revoked_direct` |
| تعديل إعداد | `settings.updated` |
| استرداد | `payment.refunded` |
| منحة كشف | `candidate.reveal_granted` |

## 7. لوحة المصفوفة (دور × صلاحية)

الشاشة المطلوبة في الوثيقة: جدول بالأدوار صفوفاً والصلاحيات أعمدة (أو العكس)، تبديل بنقرة واحدة.

**متطلّبات وظيفية:**
- تجميع الصلاحيات بحسب `المجموعة` مع طيّ/فرد.
- تمييز بصري للصلاحيات الحرجة 🔴 (تحذير قبل المنح).
- «تحديد كل المجموعة» بنقرة.
- حفظ تفاضلي (diff) لا استبدال كامل — ليُسجَّل في التدقيق ما تغيّر بدقّة.
- **حارس:** لا يستطيع المستخدم نزع `rbac.manage` عن نفسه (قفل الباب من الداخل).
- **حارس:** لا يجوز حذف آخر مستخدم يملك `super-admin`.

⬜ **الشاشة غير متوفّرة في `UI/` بعد.** المنطق أعلاه جاهز؛ التنفيذ ينتظر التصميم.

## 8. الاختبارات الملزمة

1. مستخدم بلا `candidate.reveal` → `403` على مسار الكشف.
2. مستخدم بالصلاحية عبر **دور** → ينجح.
3. مستخدم بالصلاحية **مباشرة** (بلا دور) → ينجح.
4. مستخدم بدورٍ يمنحها لكن بمنعٍ فرديّ → `403`.
5. `super-admin` → ينجح في كل شيء.
6. حذف صلاحية من دور → المستخدمون يفقدونها فوراً (إبطال الكاش).
7. `profile.moderate` يكشف الحقول → **ويكتب صفّ تدقيق**.
8. محاولة نزع `rbac.manage` عن النفس → `422`.
9. `PermissionSeeder` يُشغَّل مرّتين → لا تكرار ولا فقدان إسنادات.

---

*راجع: [`06-masking-and-reveal.md`](06-masking-and-reveal.md) للعلاقة بين Policy وResource · [`03-data-model.md`](03-data-model.md) لجداول Spatie.*
