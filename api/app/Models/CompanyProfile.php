<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationStatus;
use App\Support\EmployerName;
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
 * @property string|null $legal_name_key
 * @property string|null $brand_name_key
 * @property string|null $cr_number
 * @property VerificationStatus $verification_status
 * @property Carbon|null $verified_at
 * @property-read User $user
 * @property-read int|null $reveals_count
 * @property-read int|null $completed_reveals_count
 */
/*
 * قرارٌ أمنيّ (تعمية at-rest — مراجعة 2026): `cr_number` يبقى نصّاً صريحاً
 * عن وعي، لا إهمال. سببان:
 *   1. مبحوثٌ في لوحة المشرف (`Admin\CompanyController` بـ LIKE). التعمية
 *      بمفتاح Laravel تُنتج نصّاً مختلفاً كل مرّة (IV عشوائي)، فتكسر البحث
 *      والفهرسة — ويلزمها فهرسٌ أعمى (blind index)، وهو ثقلٌ لا يبرّره حقلٌ
 *      رقمُه شبه علنيّ (السجلّ التجاري سجلٌّ عام).
 *   2. المنصّة لا تخزّن هويّةً وطنية ولا بيانات بطاقات (PCI نظيف) — فلا PII
 *      عالي الحساسية يستدعي المخاطرة.
 *
 * ⚠️ لا تُضِف `'cr_number' => 'encrypted'` إلى casts: ستكسر البحث وتُعطب
 *    الصفوف القائمة. إن تغيّر نموذج التهديد، فالمسار: فهرسٌ أعمى + هجرة
 *    توسيعٍ وتشفيرٍ مخصّصة. وكذلك `users.phone` (محجوبٌ خلف الدفع): تعميته
 *    ممكنة لكنها تتطلّب توسيع العمود وهجرة بيانات حيّة وثبات APP_KEY.
 */
#[Fillable(['legal_name', 'brand_name', 'cr_number', 'industry_id', 'size_id', 'logo_path'])]
class CompanyProfile extends Model
{
    /** @use HasFactory<CompanyProfileFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (self $company) {
            if ($company->isDirty(['legal_name', 'brand_name'])) {
                $company->legal_name_key = EmployerName::key($company->legal_name);
                $company->brand_name_key = EmployerName::key($company->brand_name);
            }
        });

        // ★★ الثقب الذي يُغلقه هذا السطر:
        //
        //    الموظّف يكتب اسم شركته قبل أن تسجّل شركتُه في المنصّة أصلاً — وهو
        //    الحال الغالب، فالموظّفون يسبقون الشركات. فلمّا تسجّل الشركة بعده،
        //    يبقى `current_employer_company_id` فارغاً، ويظهر لها موظّفُها في
        //    أوّل صفحةٍ يتصفّحها.
        //
        //    فنربطهم بها **لحظةَ تسجيلها**، لا ننتظر أن يعود كلٌّ منهم فيحفظ
        //    ملفّه من جديد.
        static::saved(function (self $company) {
            if ($company->wasChanged(['legal_name_key', 'brand_name_key']) || $company->wasRecentlyCreated) {
                $company->claimCurrentEmployees();
            }
        });
    }

    /**
     * ينسب إلى الشركة كلَّ من كتب اسمها نصّاً حرّاً ولم يُربَط بها بعد.
     *
     * ⚠️ لا يمسّ من ربط نفسه بشركةٍ أخرى صراحةً: `whereNull` شرطٌ لا زينة.
     *    شركتان بمفتاحٍ متشابه لا يجوز أن تسرق إحداهما موظّفي الأخرى.
     */
    public function claimCurrentEmployees(): int
    {
        $keys = EmployerName::keysOf($this);

        if ($keys === []) {
            return 0;
        }

        return EmployeeProfile::query()
            ->whereNull('current_employer_company_id')
            ->whereIn('current_employer_name_key', $keys)
            ->update(['current_employer_company_id' => $this->id]);
    }

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

    /** @return HasMany<JobPosting, $this> */
    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
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
