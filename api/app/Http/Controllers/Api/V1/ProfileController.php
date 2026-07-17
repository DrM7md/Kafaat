<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApprovalStatus;
use App\Enums\AttachmentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Models\Skill;
use App\Models\User;
use App\Services\Notifications\Events;
use App\Services\Notifications\Notifier;
use App\Services\Readiness\ReadinessMapper;
use App\Support\Permissions;
use App\Support\SettingKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly ReadinessMapper $readiness,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        $profile->load(['skills', 'experiences', 'educations', 'attachments', 'field', 'country', 'nationality', 'city', 'employmentStatus', 'currentEmployer']);

        // صاحب الملفّ يرى كل شيء — لا حجب على النفس.
        return response()->json([
            'profile' => $profile,

            // حالةُ المؤشّر لا قيمتُه: القيمة في `profile.readiness`، وهذه
            // أحكامُ الخادم عليها. تُحسَب هنا لأن عتبتَي النضارة إعدادان
            // يملكهما المشرف — فلا تُعاد صياغتهما في الواجهة.
            'readiness_state' => [
                'age_days' => $profile->readinessAgeDays(),
                'needs_confirmation' => $profile->needsReadinessConfirmation(),
                'stale' => $profile->isReadinessStale(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'field_id' => ['nullable', 'exists:lookups,id'],
            'years_experience' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'country_id' => ['nullable', 'exists:lookups,id'],
            'nationality_id' => ['nullable', 'exists:lookups,id'],
            'city_id' => ['nullable', 'exists:lookups,id'],
            // ★ لا يُرسلها العميل عادةً: تُشتقّ من `readiness` أدناه. تبقى
            //   مقبولةً للمسارات التي لا تعرف المؤشّر (بذرة، أداة إدارية).
            'employment_status_id' => ['nullable', 'exists:lookups,id'],

            // مؤشّر الرغبة في الانتقال — 0 مستقرّ تماماً، 100 يبحث بجدّية.
            'readiness' => ['nullable', 'integer', 'min:0', 'max:100'],

            'expected_salary_min' => ['nullable', 'integer', 'min:0'],
            'expected_salary_max' => ['nullable', 'integer', 'min:0', 'gte:expected_salary_min'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'notice_period_clause' => ['nullable', 'string', 'max:2000'],
            'penalty_amount' => ['nullable', 'integer', 'min:0'],

            // ★ يُدخله الموظّف لنمنع شركته من رؤية ملفّه — لا لنعرضه.
            'current_employer_company_id' => ['nullable', 'exists:company_profiles,id'],
            'current_employer_name' => ['nullable', 'string', 'max:160'],

            'visibility' => ['sometimes', 'in:public,hidden'],
            'skills' => ['sometimes', 'array', 'max:20'],
            'skills.*' => ['string', 'max:80'],

            // خيار «أخرى»: نصٌّ حرّ يصير اقتراحاً معلَّقاً لا خياراً رسمياً.
            'field_other' => ['nullable', 'string', 'max:120'],
            'city_other' => ['nullable', 'string', 'max:120'],
            'nationality_other' => ['nullable', 'string', 'max:120'],
        ]);

        // «أخرى» تسبق المعرّف: من كتب مجالاً جديداً يقصده هو، لا ما اختاره قبله.
        if (filled($data['field_other'] ?? null)) {
            $data['field_id'] = $this->suggest(Lookup::FIELD, (string) $data['field_other'], $request)->id;
        }

        if (filled($data['city_other'] ?? null)) {
            $data['city_id'] = $this->suggest(
                Lookup::CITY,
                (string) $data['city_other'],
                $request,
                // المدينة المقترحة تُنسب إلى دولتها، وإلّا صارت مدينةً بلا بلد.
                $data['country_id'] ?? $profile->country_id,
            )->id;
        }

        // الكتالوج شامل، لكنّه لا يحيط: أقاليم وحالاتٌ لا تُحصى. من لم يجد
        // نفسه يكتبها، فتصير اقتراحاً معلَّقاً يراه هو وحده حتى يعتمده مشرف —
        // كالمجال والمدينة تماماً.
        if (filled($data['nationality_other'] ?? null)) {
            $data['nationality_id'] = $this->suggest(
                Lookup::NATIONALITY,
                (string) $data['nationality_other'],
                $request,
            )->id;
        }

        $profile->fill(Arr::except($data, ['skills', 'field_other', 'city_other', 'nationality_other']));

        // اختار شركته من القائمة ⇒ لا يبقى اسمٌ حرٌّ إلى جانبها: الاسم يُقرأ من
        // العلاقة. والسلسلة الفارغة ليست اسماً — تُخزَّن `null`، وإلّا عُرضت
        // للشركة جهةُ عملٍ اسمُها فراغ.
        if ($profile->current_employer_company_id !== null || blank($profile->current_employer_name)) {
            $profile->current_employer_name = null;
        }

        // العملة تُشتقّ من الدولة ولا يرسلها العميل: راتبٌ بالريال القطري في
        // ملفٍّ إماراتيّ خطأٌ لا يُصحّحه إلّا الخادم.
        if ($profile->country_id !== null) {
            $profile->currency = $this->currencyOf($profile->country_id);
        }

        // ★ الحالة الوظيفية **تُشتقّ** من المؤشّر — لا تُرسَل من الواجهة.
        //
        //   لو حسبتها الواجهةُ لأمكن لعميلٍ أن يبعث نسبة 95 وحالة «مستقرّ» معاً،
        //   فيظهر مستقرّاً في الشارة، وفي مقدّمة فرز «الراغبين في الانتقال» في
        //   الوقت نفسه. الاشتقاق في الخادم يجعل التناقض مستحيلاً لا مستبعَداً.
        if (array_key_exists('readiness', $data)) {
            $this->applyReadiness($profile);
        }

        $profile->save();

        if (isset($data['skills'])) {
            /** @var list<string> $names */
            $names = $data['skills'];

            $skillIds = array_map(
                fn (string $name): int => Skill::firstOrCreate(
                    ['name' => $name],
                    ['slug' => Str::slug($name) ?: Str::random(8)],
                )->id,
                $names,
            );

            $profile->skills()->sync($skillIds);
        }

        // بعد مزامنة المهارات: الاكتمال يعتمد عليها.
        // forceFill لأن `completeness` محسوبة من النظام لا من مدخلات المستخدم.
        $profile->forceFill(['completeness' => $this->completeness($profile)])->save();

        return response()->json([
            'profile' => $profile->fresh(['skills', 'field', 'country', 'city', 'employmentStatus', 'attachments']),
        ]);
    }

    /**
     * ★ نقرةٌ واحدة: «نعم، ما زلتُ كما أنا».
     *
     * أرخصُ فعلٍ في المنصّة وأثمنُ ما فيها. بلا هذا المسار لا سبيل لتجديد
     * الإشارة إلّا بفتح نموذج التحرير كلِّه وحفظه — احتكاكٌ يكفي ليهجره
     * الناس، فتشيخ كلُّ الحالات، ويدفع المشتري في بياناتٍ ميتة.
     *
     * ولا يُغيّر النسبة: يُعيد ختمها بتاريخ اليوم — ويُعيد اشتقاق الحالة معها،
     * فمشرفٌ عدّل عتبةً أمسِ يجب أن تسري على من يُصدّق اليوم.
     */
    public function confirmReadiness(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        if ($profile->readiness === null) {
            throw ValidationException::withMessages([
                'readiness' => 'اضبط مؤشّر الانتقال أوّلاً.',
            ]);
        }

        $this->applyReadiness($profile);
        $profile->save();

        return response()->json([
            'message' => 'جُدِّدت حالتك. تراها الشركات محدَّثةً اليوم.',
            'profile' => $profile->fresh(['employmentStatus']),
        ]);
    }

    /**
     * يشتقّ الحالة الوظيفية من المؤشّر، ويختمها بتاريخ اليوم.
     *
     * ★ الختم يقع في كل حفظٍ للمؤشّر — لا عند تغيّر قيمته وحده. الموظّف الذي
     *   فتح ملفّه اليوم ونظر في الشريط وتركه حيث هو **قد صدّقه**: أن يبقى
     *   راغباً في الترك بعد شهرين خبرٌ كأن يتغيّر. ولو اشترطنا التغيّر لتعفّنت
     *   حالةُ كلِّ من لم يتبدّل حاله — وهم أكثر الناس.
     */
    private function applyReadiness(EmployeeProfile $profile): void
    {
        if ($profile->readiness === null) {
            // مُسِح المؤشّر: يُمحى ختمُه معه. لكنّ الحالة الوظيفية تبقى — لا
            // نُسقط عن الموظّف حالةً يراها الناس دون أن يطلب ذلك.
            $profile->readiness_confirmed_at = null;

            return;
        }

        $status = $this->readiness->statusFor($profile->readiness);

        // `null` تعني أنّ المشرف لم يُسعّر أيّ حالةٍ بعتبة. لا نُخمّن حينها،
        // ولا نمسح ما عنده. ReadinessMapper §2.
        if ($status !== null) {
            $profile->employment_status_id = $status->id;
        }

        $profile->readiness_confirmed_at = now();
    }

    /**
     * نشر الملفّ.
     *
     * ★ بوّابة الكفاءات **مطفأة افتراضاً** (`moderation.require_candidate_approval`):
     *   الموظّف ينشر فيظهر فوراً. الاحتكاك هنا يطرد المرشّحين، وهم الطرف
     *   الشحيح في السوق — والشركةُ هي من يُتحقَّق منه، لأنها من يرى الناس.
     *
     *   والآلة كلّها باقية: إشعال المفتاح من اللوحة يُعيد طابورَ المراجعة
     *   بلا سطر كود، إن امتلأت المنصّة يوماً بملفّات وهمية.
     */
    public function publish(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        // الطلب المقدَّم أصلاً لا يُعاد التحقّق منه ولا يُعاد ختمه بتاريخٍ جديد:
        // وإلّا قفز صاحبُه إلى مقدّمة طابور المراجعة بضغطة زرّ.
        if ($profile->isAwaitingApproval()) {
            return response()->json([
                'message' => 'طلبك قيد المراجعة بالفعل.',
                'ref' => $profile->ref,
                'approval_status' => $profile->approval_status->value,
            ]);
        }

        if ($this->completeness($profile) < 60) {
            throw ValidationException::withMessages([
                'profile' => 'أكمل ملفّك (60% على الأقل) قبل تقديمه للاعتماد.',
            ]);
        }

        $needsApproval = (bool) settings(SettingKeys::MODERATION_REQUIRE_CANDIDATE_APPROVAL, false);

        $profile->forceFill([
            'published_at' => now(),
            'approval_status' => $needsApproval ? ApprovalStatus::Pending : ApprovalStatus::Approved,
            'approved_at' => $needsApproval ? null : now(),

            // لا `approved_by` حين ينشر تلقائياً: لا مشرفَ اعتمده، ونسبةُ
            // الاعتماد إلى إنسانٍ لم يره تُفسد سجلَّ المسؤولية.
            'approved_by' => null,
            'submitted_at' => now(),
            'rejection_reason' => null,
        ])->save();

        if ($needsApproval) {
            // ★ بالصلاحية لا بالدور: «من يستطيع الاعتماد» — أياً كان دوره.
            //   و`ref` لا اسم: هويّة المرشّح محجوبة عن المشرف أيضاً.
            $this->notifier->sendToPermission(
                Permissions::PROFILE_APPROVE,
                Events::profileSubmitted($profile->ref),
            );
        }

        return response()->json([
            'message' => $needsApproval
                ? 'قُدِّم ملفّك للاعتماد. سيظهر للشركات بعد موافقة المشرف.'
                : 'نُشر ملفّك، وصار ظاهراً للشركات. هويّتك ما زالت محجوبة حتى تدفع شركةٌ رسم الكشف.',
            'ref' => $profile->ref,
            'approval_status' => $profile->approval_status->value,
        ]);
    }

    public function unpublish(Request $request): JsonResponse
    {
        // السحب يُعيد الملفّ مسودّةً: إعادة النشر تعني طلباً جديداً.
        $this->ownProfile($request)->forceFill([
            'published_at' => null,
            'approval_status' => ApprovalStatus::Draft,
            'submitted_at' => null,
        ])->save();

        return response()->json(['message' => 'سُحب ملفّك من التصفّح.']);
    }

    public function uploadAttachment(Request $request): JsonResponse
    {
        $profile = $this->ownProfile($request);

        $data = $request->validate([
            'type' => ['required', 'in:cv,certificate,good_conduct'],

            // `mimes` يفحص محتوى الملفّ لا امتداده: ملفٌّ تنفيذيّ يُسمّى `cv.pdf`
            // يُرفض. و`jpeg` مذكورة صراحةً إلى جانب `jpg` — الامتدادان شائعان.
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,doc,docx,png,jpg,jpeg'],
        ]);

        $type = AttachmentType::from((string) $data['type']);

        // ADR-013: الميزة معطّلة افتراضياً حتى مراجعة قانونية.
        if ($type === AttachmentType::GoodConduct && ! (bool) settings(SettingKeys::FEATURE_GOOD_CONDUCT_UPLOAD, false)) {
            throw ValidationException::withMessages([
                'type' => 'رفع شهادة حُسن السيرة معطّل حالياً.',
            ]);
        }

        $file = $request->file('file');

        // قرص خاص. لا `public`. رابطٌ مباشر واحد يتجاوز الحجب كلّه.
        $path = $file->store('attachments/'.$profile->id, 'private');

        $attachment = $profile->attachments()->create([
            'type' => $type,
            'disk' => 'private',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'is_private' => true,
        ]);

        return response()->json(['attachment' => AttachmentResource::make($attachment)], 201);
    }

    /**
     * حذف مرفق — ليستبدل الموظّف سيرته الذاتية بأحدث منها.
     *
     * بلا هذا المسار تتكدّس النسخ: الرفع يُنشئ صفّاً جديداً في كل مرّة، فترى
     * الشركةُ خمسَ سيرٍ ذاتية لا تدري أيّها الأحدث.
     */
    public function deleteAttachment(Request $request, Attachment $attachment): JsonResponse
    {
        Gate::authorize('delete', $attachment);

        $profile = $this->ownProfile($request);

        // الملفّ يُمحى من القرص لا من الجدول وحده: القانون 13 يقتضي ألّا يبقى
        // أثرُ بياناتٍ حذفها صاحبها.
        Storage::disk($attachment->disk)->delete($attachment->path);

        $attachment->delete();

        // السيرة الذاتية أحدُ معايير الاكتمال — حذفها يُنقصه.
        $profile->forceFill(['completeness' => $this->completeness($profile->fresh())])->save();

        return response()->json(['message' => 'حُذف المرفق.']);
    }

    /**
     * يحوّل نصّ «أخرى» إلى صفٍّ في `lookups` بحالة `pending`.
     *
     * الصفّ حقيقيّ من فوره، فيشير إليه `field_id` مباشرةً ويرى الموظّف قيمته
     * في ملفّه. لكنّه محجوب عن قائمة الجميع حتى يعتمده مشرف — فلا يحقن أحدٌ
     * قيمةً في نماذج الناس بلا مراجعة.
     *
     * ويُعاد استخدام الاقتراح القائم بدل تكراره: لو كتب عشرةُ موظّفين
     * «مسيعيد» لكان صفٌّ واحدٌ معلَّقاً لا عشرة، ولاعتمدها المشرف مرّةً واحدة.
     */
    private function suggest(string $group, string $label, Request $request, ?int $parentId = null): Lookup
    {
        $label = Str::squish($label);
        $key = Lookup::keyFromLabel($label);

        $existing = Lookup::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        // القيمة موجودة أصلاً — معتمَدةً كانت أم معلَّقة. لا نُنشئ توأماً لها.
        if ($existing !== null) {
            return $existing;
        }

        $suggestion = Lookup::create([
            'group' => $group,
            'key' => $key,
            'label_ar' => $label,
            'parent_id' => $parentId,
            'sort_order' => 999,

            // معطَّلة ومعلَّقة: لا تظهر في قائمة أحد حتى يعتمدها مشرف.
            'is_active' => false,
            'status' => Lookup::STATUS_PENDING,
            'suggested_by_user_id' => $request->user()?->id,
        ]);

        // بلا هذا يبقى الاقتراح حبيس القاعدة حتى يفتح مشرفٌ صفحةَ القوائم صدفةً.
        $this->notifier->sendToPermission(
            Permissions::LOOKUP_MANAGE,
            Events::lookupSuggested($label, $group),
        );

        return $suggestion;
    }

    /** عملة الدولة من `meta`. القائمة هي المصدر — لا ثابتٌ في الكود. */
    private function currencyOf(int $countryId): string
    {
        $country = Lookup::query()
            ->where('group', Lookup::COUNTRY)
            ->find($countryId);

        $currency = $country?->meta['currency'] ?? null;

        return is_string($currency) ? $currency : 'QAR';
    }

    private function ownProfile(Request $request): EmployeeProfile
    {
        /** @var User $user */
        $user = $request->user();

        $profile = $user->employeeProfile;

        abort_if($profile === null, 403, 'هذا الحساب ليس حساب موظّف.');

        return $profile;
    }

    private function completeness(EmployeeProfile $profile): int
    {
        $checks = [
            $profile->title !== '',
            $profile->field_id !== null,
            $profile->city_id !== null,
            $profile->employment_status_id !== null,
            $profile->years_experience > 0,
            $profile->summary !== null,
            $profile->skills()->exists(),
            $profile->attachments()->where('type', AttachmentType::Cv)->exists(),
        ];

        return (int) round(count(array_filter($checks)) / count($checks) * 100);
    }
}
