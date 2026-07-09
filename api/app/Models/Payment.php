<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * ⚠️ لا نخزّن بيانات بطاقات إطلاقاً (PCI-DSS). البوّابة تستضيف صفحة الدفع،
 * ونحفظ `provider_ref` فقط. Brain/09 §7.
 *
 * الطوابع الزمنية (`paid_at`, `refunded_at`) خارج `Fillable` عمداً:
 * يكتبها النظام لا مدخلات المستخدم. تُضبط بـ forceFill في RevealService.
 *
 * @property PaymentStatus $status
 * @property int $amount
 * @property string $provider_ref
 */
#[Fillable([
    'company_profile_id', 'provider', 'provider_ref', 'idempotency_key',
    'amount', 'currency', 'fee', 'status', 'payload',
])]
class Payment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'payload' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    /** @return BelongsTo<CompanyProfile, $this> */
    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    /** @return HasOne<CandidateReveal, $this> */
    public function reveal(): HasOne
    {
        return $this->hasOne(CandidateReveal::class);
    }
}
