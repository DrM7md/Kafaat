<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserType;
use App\Enums\VerificationStatus;
use App\Models\CompanyProfile;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfile>
 */
class CompanyProfileFactory extends Factory
{
    protected $model = CompanyProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['type' => UserType::Company])
                ->afterCreating(fn (User $u) => $u->assignRole(Roles::COMPANY)),
            'legal_name' => fake()->company(),

            // إجباريٌّ في التسجيل وفي حفظ الحساب، فيلزم أن تُنتجه المصانع كذلك:
            // شركةٌ بلا سجلٍّ لا تُنشأ في الإنتاج، فلا تُختبَر عليها الفروع.
            'cr_number' => (string) fake()->numerify('##########'),

            // ★ موثَّقة افتراضاً: أغلب الاختبارات تسأل عن الحجب والكشف والمال،
            //   وتلزمها شركةٌ **تستطيع** استعمال المنصّة. شركةٌ افتراضية محجوبة
            //   عن التصفّح كانت ستجعل كل اختبارٍ يبدأ بتوثيقها — ضجيجٌ يُخفي
            //   ما يُختبَر فعلاً. بوّابةُ التوثيق نفسها تُختبَر بـ `pending()`.
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
        ];
    }

    /** شركةٌ سجّلت للتوّ وتنتظر قرار المشرف — الحالة التي يُنتجها التسجيل. */
    public function pending(): self
    {
        return $this->state([
            'verification_status' => VerificationStatus::Pending,
            'verified_at' => null,
        ]);
    }

    public function rejected(): self
    {
        return $this->state([
            'verification_status' => VerificationStatus::Rejected,
            'verified_at' => null,
        ]);
    }
}
