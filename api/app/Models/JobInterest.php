<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إعجاب موظّفٍ بوظيفة.
 *
 * ★★ **لا يكشف هوية.** الشركة ترى بطاقةً محجوبة وزرَّ كشف، تماماً كالتصفّح.
 *    ولولا ذلك لانهار نموذج الربح: تكفي الشركةَ أن تنشر وظيفةً فيأتيها الناس
 *    بأسمائهم بلا ريال واحد.
 *
 *    وما يضيفه الإعجاب أثمنُ من الاسم: **النيّة**. الشركة تعرف أنّ هذا المرشّح
 *    يريد وظيفتَها — فتدفع رسم الكشف عن رغبةٍ لا مخاطرة.
 *
 * @property int $job_posting_id
 * @property int $employee_profile_id
 */
class JobInterest extends Model
{
    protected $fillable = ['job_posting_id', 'employee_profile_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'withdrawn_at' => 'datetime',
            'seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<JobPosting, $this> */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /** @return BelongsTo<EmployeeProfile, $this> */
    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    /**
     * الإعجابات القائمة — لا المنسحَب منها.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('withdrawn_at');
    }

    public function isActive(): bool
    {
        return $this->withdrawn_at === null;
    }
}
