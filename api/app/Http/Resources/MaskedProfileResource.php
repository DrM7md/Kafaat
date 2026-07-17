<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Education;
use App\Models\EmployeeProfile;
use App\Models\Experience;
use App\Services\Masking\FieldSet;
use App\Services\Masking\ProfileVisibilityService;
use App\Services\Reveal\RevealPricing;
use App\Support\SettingKeys;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ★ الحاجز الأخير قبل الشبكة.
 *
 * لا يبني الاستجابة من النموذج، بل من `FieldSet`. كل حقلٍ محجوب
 * **يختفي من المصفوفة**، ولا يُرسَل فارغاً ولا مشوّشاً.
 *
 * ⚠️ نموذج التصميم (UI/*.dc.html) يرسل الاسم ويشوّشه بـ CSS `blur(6px)`.
 *    مقبولٌ في نموذج، وكارثة في الإنتاج: يُقرأ بنقرة في أدوات المطوّر.
 *    لا تنسخه هنا. ADR-003.
 *
 * @mixin EmployeeProfile
 */
final class MaskedProfileResource extends JsonResource
{
    public static $wrap = 'data';

    private bool $detailed = false;

    public function detailed(): self
    {
        $this->detailed = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fields = app(ProfileVisibilityService::class)
            ->visibleFields($request->user(), $this->resource);

        return $fields->filter([
            // ── مكشوف دائماً: جوهر العرض وأساس الفلترة ──
            'ref' => $this->ref,
            'title' => $this->title,
            'field' => $this->whenLoaded('field', fn () => LookupResource::make($this->field)),
            'years_experience' => $this->years_experience,
            'city' => $this->whenLoaded('city', fn () => $this->city?->label_ar),

            // الجنسية مكشوفة كالمدينة والمجال: صفةٌ مهنية تُصفّى بها الشركة
            // بحثَها (تأشيرة، توطين) — لا تُعرّف صاحبَها ولا تدلّ عليه.
            'nationality' => $this->whenLoaded('nationality', fn () => LookupResource::make($this->nationality)),

            'employment_status' => $this->whenLoaded(
                'employmentStatus',
                fn () => LookupResource::make($this->employmentStatus),
            ),

            // ★★ نضارة الحالة — لا الرقم الذي اشتُقّت منه.
            //
            //   `readiness` (0–100) **لا يخرج من الخادم أبداً**. عرضُ «87/100»
            //   يوهم الشركةَ بدقّةٍ لا وجود لها، ويُشعر الموظّف بأنّه مُقيَّم
            //   برقم. شارةٌ لا عدد — كشارة الطلب في `demandTier()` تماماً.
            //
            //   أمّا **عمرُ** الشارة فيُعرض صراحةً، لأنّه ما يُميّز الخبر من
            //   الذكرى: الشركة تدفع رسم الكشف على أساس هذه الشارة، فمن حقّها
            //   أن تعرف أنّ صاحبها أقرّها أمس لا قبل ثمانية أشهر.
            'status_age_days' => $this->readinessAgeDays(),
            'status_stale' => $this->isReadinessStale(),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->pluck('name')->all()),
            'expected_salary' => $this->salaryRange(),
            'is_featured' => (bool) $this->is_featured,
            'is_revealed' => $fields->isFullyVisible(),

            // السعر من مجال الملفّ. الموضع الوحيد الذي يُحسَب فيه هو
            // `RevealPricing` — الواجهة تعرض ما يخصمه الخادم تماماً.
            'reveal_fee' => app(RevealPricing::class)->forProfile($this->resource),

            // منذ متى وهو معروضٌ للشركات. من تاريخ الاعتماد لا إنشاء الحساب:
            // من ظلّ ملفّه مسودّةً ستّة أشهر ليس «قديماً في السوق».
            'listed_since_days' => $this->listedSinceDays(),

            // ★ شارةٌ لا عدد. إرسال «كشفته 5 شركات» يُخبر الشركةَ بنشاط
            //   منافسيها، ويصِم من لم يُطلَب بعدُ بـ «0».
            'demand' => $this->demandTier(),

            ...$this->detailedFields($fields),

            // ── تختفي كلّياً إن لم تكن في FieldSet ──
            'name' => $this->user->name,
            'initials' => $this->initials(),
            'phone' => $this->user->phone,
            'email' => $this->user->email,
            'current_employer' => $this->current_employer_name
                ?? $this->currentEmployer?->legal_name,
            'notice_period_clause' => $this->notice_period_clause,
            'penalty_amount' => $this->penalty_amount,
            'attachments' => $this->whenLoaded(
                'attachments',
                fn () => AttachmentResource::collection($this->attachments),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function detailedFields(FieldSet $fields): array
    {
        if (! $this->detailed) {
            return [];
        }

        $unmasked = $fields->isFullyVisible();
        $maskCurrentEmployer = (bool) settings(SettingKeys::MASKING_CURRENT_EMPLOYER_IN_EXPERIENCE, true);

        return [
            'summary' => $this->summary,

            'experiences' => $this->whenLoaded(
                'experiences',
                fn (): array => array_map(
                    fn (Experience $e): array => $this->experienceRow($e, $unmasked, $maskCurrentEmployer),
                    $this->experiences->all(),
                ),
            ),

            'educations' => $this->whenLoaded(
                'educations',
                fn (): array => array_map(
                    fn (Education $e): array => [
                        'institution' => $e->institution,
                        'degree' => $e->degree,
                        'field_of_study' => $e->field_of_study,
                        'graduated_year' => $e->graduated_year,
                    ],
                    $this->educations->all(),
                ),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function experienceRow(Experience $e, bool $unmasked, bool $maskCurrentEmployer): array
    {
        $row = [
            'title' => $e->title,
            'is_current' => $e->is_current,
            'started_on' => $e->started_on->toDateString(),
            'ended_on' => $e->ended_on?->toDateString(),
            'description' => $e->description,
            'company_name' => $e->company_name,
        ];

        // اسم جهة العمل **الحالية** محجوب: يعرّض الموظّف لصاحب عمله.
        // الخبرات السابقة مكشوفة — الماضي لا يعرّضه للخطر.
        if ($e->is_current && $maskCurrentEmployer && ! $unmasked) {
            unset($row['company_name']);
        }

        return $row;
    }

    /**
     * كم يوماً مضى وهو معروضٌ للشركات.
     *
     * من `approved_at` لا من `users.created_at`: الحساب قد يُنشأ ويبقى ملفّه
     * مسودّةً شهوراً، فعرضُ «مسجّل منذ 300 يوماً» يصِمه بالبيات وهو للتوّ
     * دخل السوق. ونرجع إلى `published_at` للملفّات السابقة لدورة الاعتماد.
     */
    private function listedSinceDays(): ?int
    {
        $listedAt = $this->approved_at ?? $this->published_at;

        if ($listedAt === null) {
            return null;
        }

        // `diffInDays` تُعيد float في Carbon 3. نُقارن بدايةَ اليوم ببدايته
        // فيصير الفرق أياماً صحيحة، ولا يقفز العدّاد بساعةٍ في منتصف الليل.
        return (int) $listedAt->startOfDay()->diffInDays(now()->startOfDay());
    }

    /**
     * مستوى الطلب — شارةٌ لا رقم.
     *
     * ★ `reveals_count` يُحسَب في الاستعلام (`withCount`) لا هنا: بلا ذلك
     *   استعلامٌ لكل مرشّح في الصفحة. وإن لم يُحمَّل، لا شارة — لا استعلامَ
     *   خفيّاً يتسلّل.
     *
     * ⚠️ العدد نفسه **لا يُرسَل**. هذه الدالّة تُعيد `null` أو نصّاً.
     */
    private function demandTier(): ?string
    {
        // نفحص وجود السمة لا قيمتَها: `withCount` قد لا يكون طُبِّق (مسارٌ آخر
        // يبني الـ Resource). حينها لا شارة — ولا استعلامَ خفيّاً يتسلّل.
        if (! array_key_exists('reveals_count', $this->resource->getAttributes())) {
            return null;
        }

        $count = (int) $this->resource->getAttributes()['reveals_count'];

        $high = (int) settings(SettingKeys::DEMAND_BADGE_HIGH_MIN, 7);
        $min = (int) settings(SettingKeys::DEMAND_BADGE_MIN, 3);

        if ($count >= $high) {
            return 'high';
        }

        // دون العتبة: لا شارة. من لم يُطلَب بعدُ لا يُوصَم بـ «0».
        return $count >= $min ? 'rising' : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function salaryRange(): ?array
    {
        if ($this->expected_salary_min === null && $this->expected_salary_max === null) {
            return null;
        }

        return [
            'min' => $this->expected_salary_min,
            'max' => $this->expected_salary_max,
            'currency' => $this->currency,
        ];
    }
}
