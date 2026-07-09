<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevealSource;
use App\Enums\RevealStatus;
use Database\Factories\CandidateRevealFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * سجلّ الكشف — قلب نموذج الربح.
 *
 * ⚠️ `status = Completed` وحدها تفكّ الحجب. تُكتب هذه الحالة داخل معاملة
 * الـ webhook فقط، ولا يكتبها متحكّم أبداً. Brain/06 §6.
 *
 * `amount` نسخة مجمّدة من رسم الكشف وقت التنفيذ (ADR-006) — لا يُقرأ من
 * الإعدادات لاحقاً، وإلّا تغيّرت الفواتير التاريخية أثراً رجعياً.
 *
 * @property RevealStatus $status
 * @property RevealSource $source
 * @property int $amount
 * @property string $currency
 * @property Carbon|null $revealed_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $expires_at
 * @property-read EmployeeProfile $employeeProfile
 */
class CandidateReveal extends Model
{
    /** @use HasFactory<CandidateRevealFactory> */
    use HasFactory;

    /** `active_reveal_key` عمود مولَّد في قاعدة البيانات — لا يُكتب من التطبيق. */
    protected $fillable = [
        'company_profile_id', 'employee_profile_id', 'payment_id',
        'source', 'amount', 'currency', 'status', 'idempotency_key',
        'revealed_at', 'refunded_at', 'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RevealStatus::class,
            'source' => RevealSource::class,
            'revealed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'expires_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    /** @return BelongsTo<CompanyProfile, $this> */
    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    /** @return BelongsTo<EmployeeProfile, $this> */
    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * كشفٌ صالح: مكتمل، ولم تنقضِ صلاحيته.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->where('status', RevealStatus::Completed)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isValid(): bool
    {
        return $this->status->unmasks()
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
