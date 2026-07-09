<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProfileVisibility;
use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ref
 * @property string $title
 * @property int $years_experience
 * @property int|null $current_employer_company_id
 * @property ProfileVisibility $visibility
 */
#[Fillable([
    'title', 'field_id', 'years_experience', 'city_id', 'employment_status_id',
    'expected_salary_min', 'expected_salary_max', 'currency', 'summary',
    'notice_period_clause', 'penalty_amount',
    'current_employer_company_id', 'current_employer_name',
    'visibility',
])]
class EmployeeProfile extends Model
{
    /** @use HasFactory<EmployeeProfileFactory> */
    use HasFactory, SoftDeletes;

    /** أبجدية بلا 0/O/1/I — تمنع اللبس عند القراءة والنطق. */
    private const REF_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const REF_LENGTH = 8;

    protected static function booted(): void
    {
        static::creating(function (self $profile) {
            $profile->ref ??= self::generateRef();
        });
    }

    /**
     * ADR-005: معرّف عامّ عشوائي غير متسلسل.
     * المعرّف التسلسلي يتيح تعداد كل الملفّات وحصر حجم القاعدة ونموّها.
     */
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
            'visibility' => ProfileVisibility::class,
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'employment_status_id');
    }

    /** @return BelongsTo<CompanyProfile, $this> */
    public function currentEmployer(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'current_employer_company_id');
    }

    /** @return BelongsToMany<Skill, $this> */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    /** @return HasMany<Experience, $this> */
    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
    }

    /** @return HasMany<Education, $this> */
    public function educations(): HasMany
    {
        return $this->hasMany(Education::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** @return HasMany<CandidateReveal, $this> */
    public function reveals(): HasMany
    {
        return $this->hasMany(CandidateReveal::class);
    }

    /**
     * الملفّات القابلة للتصفّح: منشورة وظاهرة فقط.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBrowsable(Builder $query): void
    {
        $query->where('visibility', ProfileVisibility::Public)
            ->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null
            && $this->visibility === ProfileVisibility::Public;
    }

    /**
     * الحروف الأولى — 🔒 حقل محجوب.
     *
     * قد تبدو غير معرِّفة، لكن اجتماعها مع المسمّى والمدينة وسنوات الخبرة
     * يكفي لتحديد الشخص في سوقٍ بحجم قطر. لا تُرسَل دون كشف صالح.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim($this->user->name)) ?: [];

        return collect($parts)
            ->take(2)
            ->map(fn (string $part): string => Str::substr($part, 0, 1))
            ->implode('.');
    }
}
