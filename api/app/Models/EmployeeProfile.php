<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\ProfileVisibility;
use App\Support\EmployerName;
use App\Support\SettingKeys;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ref
 * @property string $title
 * @property int $years_experience
 * @property int|null $current_employer_company_id
 * @property string|null $current_employer_name
 * @property string|null $current_employer_name_key
 * @property int|null $readiness
 * @property Carbon|null $readiness_confirmed_at
 * @property ProfileVisibility $visibility
 * @property ApprovalStatus $approval_status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $published_at
 * @property int $completeness
 */
#[Fillable([
    'title', 'field_id', 'years_experience', 'country_id', 'nationality_id', 'city_id', 'employment_status_id',
    'expected_salary_min', 'expected_salary_max', 'currency', 'summary',
    'notice_period_clause', 'penalty_amount',
    'current_employer_company_id', 'current_employer_name',
    'readiness',
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

        // ★ الربط في النموذج لا في المتحكّم: بذرةٌ أو أمرُ صيانةٍ أو مسارٌ
        //   مستقبَليّ يكتب الاسم — كلّها تمرّ من هنا. قاعدةُ حجبٍ تعتمد على
        //   أن يتذكّرها كلُّ كاتبٍ للحقل هي قاعدةٌ ستُنسى مرّة.
        static::saving(function (self $profile) {
            if ($profile->isDirty(['current_employer_name', 'current_employer_company_id'])) {
                $profile->linkCurrentEmployer();
            }
        });
    }

    /**
     * يشتقّ مفتاح المطابقة من الاسم الحرّ، ويربطه بالشركة المسجَّلة إن وُجدت.
     *
     * الموظّف يكتب «شركة الخليج للتقنية» ولا يعرف أنّها مسجَّلة عندنا باسم
     * «الخليج للتقنية ذ.م.م». المفتاح يجمعهما، فيمتلئ `current_employer_company_id`
     * — وعليه تقوم آلةُ الحجب كلّها (التصفّح، والكشف، ولوحة الوظائف).
     */
    public function linkCurrentEmployer(): void
    {
        $this->current_employer_name_key = EmployerName::key($this->current_employer_name);

        // ★★ اختار شركته من القائمة صراحةً؟ نأخذ مفتاحَ **اسمها** أيضاً.
        //
        //    بلا هذا كان الاختيار الصريح **أقلّ حمايةً** من الكتابة الحرّة —
        //    عكسَ المقصود تماماً:
        //
        //      • من كتب «الخليج للتقنية» نصّاً، حُجب عن كلّ شركةٍ يُطابق اسمُها
        //        هذا المفتاح — ولو سجّلت مرّتين بصيغتين.
        //      • ومن اختار «الخليج للتقنية ذ.م.م» من القائمة، بقي مفتاحُ اسمه
        //        فارغاً، فانكشف لـ «شركة الخليج للتقنية» — وهي هي.
        //
        //    والتسجيل المكرَّر واقعٌ لا فرض: شركةٌ يسجّلها فرعان أو مديران
        //    بصيغتين مختلفتين. فنحجبه عن كلّ من يحمل مفتاحها.
        //
        // ⚠️ نعم، قد يحجبه هذا عن شركةٍ **غير** صاحبة عمله تصادف اسمُها اسمَها.
        //    وذلك ثمنٌ مقبول: أن نحجب ملفّاً عن شركةٍ ليست صاحبةَ عمله خسارةُ
        //    فرصةٍ تجارية، وأن نكشفه لصاحب عمله قد يُفقده وظيفته.
        if ($this->current_employer_company_id !== null) {
            $employer = CompanyProfile::query()->find($this->current_employer_company_id);

            $this->current_employer_name_key = $employer !== null
                ? (EmployerName::keysOf($employer)[0] ?? null)
                : null;

            return;
        }

        if ($this->current_employer_name_key === null) {
            return;
        }

        $this->current_employer_company_id = CompanyProfile::query()
            ->where('legal_name_key', $this->current_employer_name_key)
            ->orWhere('brand_name_key', $this->current_employer_name_key)
            ->value('id');
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
            'approval_status' => ApprovalStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'readiness_confirmed_at' => 'datetime',
        ];
    }

    /**
     * كم يوماً مضى على آخر تصديقٍ لمؤشّر الانتقال.
     *
     * `null` لمن لم يضبطه قطّ — لا صفر: «لم يُسأل بعد» غيرُ «صدّقه اليوم».
     */
    public function readinessAgeDays(): ?int
    {
        if ($this->readiness_confirmed_at === null) {
            return null;
        }

        // بدايةُ اليوم ببدايته: `diffInDays` تُعيد float في Carbon 3، ولو
        // قارنّا اللحظةَ باللحظة لقفز العدّاد يوماً في منتصف الليل.
        return (int) $this->readiness_confirmed_at->startOfDay()->diffInDays(now()->startOfDay());
    }

    /**
     * ★ هل بارت الإشارة؟
     *
     * حالةٌ عمرُها ثمانية أشهر ليست خبراً — هي ذكرى. والشركة تدفع رسم الكشف
     * على أساسها. فبعد العتبة تُطفَأ الشارةُ ولا تُعرض كأنّها اليوم.
     */
    public function isReadinessStale(): bool
    {
        $age = $this->readinessAgeDays();

        return $age !== null && $age >= (int) settings(SettingKeys::READINESS_STALE_DAYS, 90);
    }

    /** هل حان وقت أن نسأله «ما زلتَ كما أنت؟» — دون أن تكون قد بارت بعد. */
    public function needsReadinessConfirmation(): bool
    {
        $age = $this->readinessAgeDays();

        return $age !== null && $age >= (int) settings(SettingKeys::READINESS_FRESH_DAYS, 45);
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

    /** الدولة — منها تُقرأ عملة الراتب. لا «QAR» مكتوبة في الكود. */
    /** @return BelongsTo<Lookup, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'country_id');
    }

    /** الجنسية — من هو، لا أين يعمل. راجع `Lookup::NATIONALITY`. */
    /** @return BelongsTo<Lookup, $this> */
    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'nationality_id');
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

    /** @return HasMany<JobInterest, $this> */
    public function jobInterests(): HasMany
    {
        return $this->hasMany(JobInterest::class);
    }

    /**
     * الملفّات القابلة للتصفّح: ظاهرة · منشورة · **ومعتمَدة**.
     *
     * ★ الشروط الثلاثة معاً. طلبٌ قُدِّم ولم يُعتمَد بعد لا يظهر لأي شركة.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBrowsable(Builder $query): void
    {
        $query->where('visibility', ProfileVisibility::Public)
            ->whereNotNull('published_at')
            ->where('approval_status', ApprovalStatus::Approved);
    }

    /**
     * ★★ عمى صاحب العمل — يُقصي من الاستعلام كلَّ من يعمل حالياً لدى الشركة
     *    الطالبة، بالمعرّف الصريح وبمفتاح الاسم المطبَّع معاً (شبكةُ أمان).
     *
     * يُطبَّق في **الاستعلام** لا في الترشيح بعده: لو رُشّح بعد العدّ لسُرّب
     * وجودُ الملفّ عبر فرق عدد النتائج؛ ولو تُرك لـ `MaskedProfileResource`
     * لرمى `ProfileNotVisibleException` أثناء تسلسل المجموعة فأفشل الاستجابة
     * كلَّها بـ 404. لذا يُستدعى في كل استعلامٍ يُري ملفّاتِ الناس لشركة:
     * التصفّح، ومِصفاة الجنسيات، وقائمة المعجبين بوظيفة.
     *
     * لا شركة (مشرفٌ أو موظّف) أو المفتاح الإداريّ مُطفأ ⇒ لا-عملية.
     *
     * ⚠️ `whereNull` صريحةً قبل `whereNotIn`: لولاها لأسقطت SQL كلَّ صفٍّ
     *    مفتاحُه `NULL` (فـ `NULL NOT IN (…)` = `NULL` لا `TRUE`) — أي كلّ من
     *    لم يذكر جهة عمله. الحجب كان سيبتلع الجميع.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBlindTo(Builder $query, ?CompanyProfile $company): void
    {
        if ($company === null || ! (bool) settings(SettingKeys::PRIVACY_EMPLOYER_BLIND, true)) {
            return;
        }

        // الطبقة الأولى: الرابط الصريح.
        $query->where(function (Builder $q) use ($company) {
            $q->whereNull('current_employer_company_id')
                ->orWhere('current_employer_company_id', '!=', $company->id);
        });

        // الطبقة الثانية: الاسم المطبَّع — لو أفلت الربطُ بالمعرّف مرّةً.
        $keys = EmployerName::keysOf($company);

        if ($keys !== []) {
            $query->where(function (Builder $q) use ($keys) {
                $q->whereNull('current_employer_name_key')
                    ->orWhereNotIn('current_employer_name_key', $keys);
            });
        }
    }

    /** هل هذا الملفّ ظاهرٌ للشركات فعلاً؟ */
    public function isPublished(): bool
    {
        return $this->published_at !== null
            && $this->visibility === ProfileVisibility::Public
            && $this->approval_status->isBrowsable();
    }

    public function isAwaitingApproval(): bool
    {
        return $this->approval_status === ApprovalStatus::Pending;
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
