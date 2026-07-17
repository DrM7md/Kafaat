<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * وظيفة كما تُعرَض.
 *
 * ⚠️ اسم الشركة **مكشوف** — وهذا ليس تناقضاً مع الحجب: الحجب يحمي **الموظّف**
 *    من صاحب عمله، لا الشركةَ من الباحثين. من ينشر وظيفةً يريد أن يُعرَف.
 *
 * ★ `interests_count` لا يُرسَل إلّا لصاحب الوظيفة. المرشّح لا يحتاج أن يعرف
 *   كم منافسٍ سبقه — والعدد يُثبّطه أو يُغريه، وكلاهما تشويشٌ لا خدمة.
 *
 * @mixin JobPosting
 */
final class JobPostingResource extends JsonResource
{
    public static $wrap = null;

    private bool $owner = false;

    /** عرض صاحب الوظيفة: يرى المسودّات، وعدد المعجبين، وحالة الخانة. */
    public function asOwner(): self
    {
        $this->owner = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = [
            'ref' => $this->ref,
            'title' => $this->title,
            'description' => $this->description,

            'company' => $this->whenLoaded('company', fn (): array => [
                'legal_name' => $this->company->legal_name,
                'brand_name' => $this->company->brand_name,
            ]),

            'field' => $this->whenLoaded('field', fn () => LookupResource::make($this->field)),
            'city' => $this->whenLoaded('city', fn () => $this->city?->label_ar),
            'min_experience' => $this->min_experience,
            'salary' => $this->salaryRange(),
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),

            // هل أبدى هذا الموظّفُ إعجابَه؟ تُحمَّل في المتحكّم بـ `withExists`.
            'has_interest' => $this->when(
                array_key_exists('has_interest', $this->resource->getAttributes()),
                fn (): bool => (bool) $this->resource->getAttributes()['has_interest'],
            ),
        ];

        if (! $this->owner) {
            return $base;
        }

        return [
            ...$base,
            'is_paid_slot' => (bool) $this->is_paid_slot,
            'closed_at' => $this->closed_at?->toIso8601String(),

            // عدد المعجبين — لصاحب الوظيفة وحده.
            'interests_count' => $this->whenCounted('interests'),
            'new_interests_count' => $this->when(
                array_key_exists('new_interests_count', $this->resource->getAttributes()),
                fn (): int => (int) $this->resource->getAttributes()['new_interests_count'],
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function salaryRange(): ?array
    {
        if ($this->salary_min === null && $this->salary_max === null) {
            return null;
        }

        return [
            'min' => $this->salary_min,
            'max' => $this->salary_max,
            'currency' => $this->currency,
        ];
    }
}
