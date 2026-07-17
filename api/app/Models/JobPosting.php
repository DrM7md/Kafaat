<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * وظيفة تنشرها شركة.
 *
 * @property int $id
 * @property string $ref
 * @property int $company_profile_id
 * @property string $title
 * @property string $description
 * @property JobStatus $status
 * @property bool $is_paid_slot
 * @property int $min_experience
 * @property int|null $salary_min
 * @property int|null $salary_max
 * @property string $currency
 * @property Carbon|null $published_at
 * @property Carbon|null $closed_at
 */
#[Fillable([
    'title', 'description', 'field_id', 'country_id', 'city_id',
    'min_experience', 'salary_min', 'salary_max',
])]
class JobPosting extends Model
{
    use SoftDeletes;

    /** أبجدية بلا 0/O/1/I — تمنع اللبس عند القراءة والنطق. */
    private const REF_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const REF_LENGTH = 8;

    protected static function booted(): void
    {
        static::creating(function (self $job) {
            $job->ref ??= self::generateRef();
        });
    }

    /** ADR-005: معرّف عامّ عشوائي. المتسلسل يُتيح تعداد وظائف المنصّة كلّها. */
    public static function generateRef(): string
    {
        do {
            $ref = '';
            for ($i = 0; $i < self::REF_LENGTH; $i++) {
                $ref .= self::REF_ALPHABET[random_int(0, strlen(self::REF_ALPHABET) - 1)];
            }
        } while (self::withTrashed()->where('ref', $ref)->exists());

        return $ref;
    }

    public function getRouteKeyName(): string
    {
        return 'ref';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'is_paid_slot' => 'boolean',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CompanyProfile, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'company_profile_id');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'field_id');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'city_id');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'country_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return HasMany<JobInterest, $this> */
    public function interests(): HasMany
    {
        return $this->hasMany(JobInterest::class);
    }

    /**
     * ما يراه الموظّفون: المفتوحة وحدها.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', JobStatus::Open)->whereNotNull('published_at');
    }

    public function isOpen(): bool
    {
        return $this->status === JobStatus::Open;
    }
}
