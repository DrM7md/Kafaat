<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationStatus;
use Database\Factories\CompanyProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $legal_name
 * @property string|null $brand_name
 * @property string|null $cr_number
 * @property VerificationStatus $verification_status
 * @property Carbon|null $verified_at
 * @property-read User $user
 * @property-read int|null $reveals_count
 * @property-read int|null $completed_reveals_count
 */
#[Fillable(['legal_name', 'brand_name', 'cr_number', 'industry_id', 'size_id', 'logo_path'])]
class CompanyProfile extends Model
{
    /** @use HasFactory<CompanyProfileFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Lookup, $this> */
    public function industry(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'industry_id');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function size(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'size_id');
    }

    /** @return HasMany<CandidateReveal, $this> */
    public function reveals(): HasMany
    {
        return $this->hasMany(CandidateReveal::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** الموظّفون الذين أعلنوا أن هذه الشركة جهةُ عملهم الحالية. */
    /** @return HasMany<EmployeeProfile, $this> */
    public function currentEmployees(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class, 'current_employer_company_id');
    }
}
