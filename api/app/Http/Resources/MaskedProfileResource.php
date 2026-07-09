<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Education;
use App\Models\EmployeeProfile;
use App\Models\Experience;
use App\Services\Masking\FieldSet;
use App\Services\Masking\ProfileVisibilityService;
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
            'employment_status' => $this->whenLoaded(
                'employmentStatus',
                fn () => LookupResource::make($this->employmentStatus),
            ),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->pluck('name')->all()),
            'expected_salary' => $this->salaryRange(),
            'is_featured' => (bool) $this->is_featured,
            'is_revealed' => $fields->isFullyVisible(),
            'reveal_fee' => (int) settings(SettingKeys::REVEAL_FEE, 0),

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
