<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RevealSource;
use App\Enums\RevealStatus;
use App\Models\CandidateReveal;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateReveal>
 */
class CandidateRevealFactory extends Factory
{
    protected $model = CandidateReveal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_profile_id' => CompanyProfile::factory(),
            'employee_profile_id' => EmployeeProfile::factory(),
            'source' => RevealSource::Payment,
            'amount' => 75,
            'currency' => 'QAR',
            'status' => RevealStatus::Pending,
        ];
    }

    public function completed(): self
    {
        return $this->state([
            'status' => RevealStatus::Completed,
            'revealed_at' => now(),
        ]);
    }
}
