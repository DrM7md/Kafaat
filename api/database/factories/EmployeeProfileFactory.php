<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\ProfileVisibility;
use App\Enums\UserType;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    protected $model = EmployeeProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['type' => UserType::Employee])
                ->afterCreating(fn (User $u) => $u->assignRole(Roles::EMPLOYEE)),
            'title' => fake()->jobTitle(),
            'years_experience' => fake()->numberBetween(1, 20),
            'expected_salary_min' => 15000,
            'expected_salary_max' => 25000,
            'summary' => fake()->sentence(),
            'visibility' => ProfileVisibility::Public,
            'completeness' => 80,
            'published_at' => now(),

            // الافتراضي معتمَد — أغلب الاختبارات تحتاج ملفّاً قابلاً للتصفّح.
            'approval_status' => ApprovalStatus::Approved,
            'approved_at' => now(),
        ];
    }

    public function unpublished(): self
    {
        return $this->state(['published_at' => null, 'approval_status' => ApprovalStatus::Draft]);
    }

    /** طلبٌ قُدِّم وينتظر مراجعة المشرف. */
    public function awaitingApproval(): self
    {
        return $this->state([
            'approval_status' => ApprovalStatus::Pending,
            'submitted_at' => now(),
            'approved_at' => null,
        ]);
    }

    public function rejected(): self
    {
        return $this->state([
            'approval_status' => ApprovalStatus::Rejected,
            'rejection_reason' => 'النبذة غير كافية.',
        ]);
    }

    public function employedBy(int $companyProfileId): self
    {
        return $this->state(['current_employer_company_id' => $companyProfileId]);
    }
}
