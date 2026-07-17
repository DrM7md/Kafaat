<?php

declare(strict_types=1);

namespace App\Services\Jobs;

use App\Enums\JobStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\CompanyProfile;
use App\Models\JobPosting;
use App\Models\Payment;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\PaymentGateway;
use App\Support\SettingKeys;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * حصّة الوظائف المفتوحة، وشراء خانةٍ إضافية.
 *
 * ★ الحصّة تُعدّ **المفتوحة** وحدها. إغلاق وظيفةٍ يحرّر خانتها — فيُثاب من
 *   ينظّف لوحته، ولا يمتلئ الموقع بوظائف ميتة تُغرق الباحثين عن عمل.
 *
 * ★★ والخانة المشتراة تبقى معلَّمةً في الوظيفة إلى الأبد (`is_paid_slot`).
 *    فإغلاقُها ثم فتحُ وظيفةٍ أخرى **لا يُلزم الشركةَ بالدفع مرّتين** عمّا
 *    دفعت فيه أصلاً: خاناتها المدفوعة تُضاف إلى حصّتها المجّانية.
 */
final class JobSlotService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * ملخّص خانات الشركة — ما تعرضه اللوحة، وما يفحصه `open()`.
     *
     * السعة = المجّانية + كل خانةٍ دُفع ثمنها، سواءٌ **استُهلكت** في وظيفة أو
     * **بقيت رصيداً** لم تُستعمل بعد.
     *
     * ★ الفرق بينهما يلزم: شركةٌ دفعت ثم أغلقت التبويب لها رصيدٌ محفوظ، ولا
     *   يجوز أن تُطالَب بالدفع مرّتين حين تعود.
     *
     * @return array{free: int, purchased: int, credit: int, total: int, used: int, remaining: int, extra_fee: int}
     */
    public function summary(CompanyProfile $company): array
    {
        $free = max(0, (int) settings(SettingKeys::JOB_FREE_SLOTS, 10));

        // خاناتٌ دُفع ثمنها واستُهلكت في وظيفة — تبقى في السعة ولو أُغلقت
        // وظائفها. المال دُفع مرّةً واحدة.
        $purchased = JobPosting::query()
            ->where('company_profile_id', $company->id)
            ->where('is_paid_slot', true)
            ->count();

        // رصيدٌ مدفوع لم يُستعمل بعد.
        $credit = $this->unusedPaidSlots($company)->count();

        $used = $this->openCount($company);
        $total = $free + $purchased + $credit;

        return [
            'free' => $free,
            'purchased' => $purchased,
            'credit' => $credit,
            'total' => $total,
            'used' => $used,
            'remaining' => max(0, $total - $used),
            'extra_fee' => (int) settings(SettingKeys::JOB_EXTRA_SLOT_FEE, 0),
        ];
    }

    /**
     * يفتح وظيفةً إن كانت هناك سعة — ويستهلك رصيداً مدفوعاً إن لزم.
     *
     * ★ الموضع **الوحيد** الذي تُفتح فيه وظيفة. لو فُتحت في متحكّم لتجاوزت
     *   الحصّة، ولو فُتحت في مكانين لتباعد المنطقان.
     *
     * @return bool `false` إن نفدت السعة — على المتحكّم أن يعرض الشراء
     */
    public function open(JobPosting $job): bool
    {
        $company = $job->company;

        // سعةٌ قائمة (مجّانية أو خانةٌ مدفوعة سابقة تحرّرت بإغلاق وظيفتها).
        $capacityWithoutCredit = $this->summary($company)['free']
            + JobPosting::query()
                ->where('company_profile_id', $company->id)
                ->where('is_paid_slot', true)
                ->where('id', '!=', $job->id)
                ->count();

        if ($this->openCount($company) < $capacityWithoutCredit) {
            $job->forceFill([
                'status' => JobStatus::Open,
                'published_at' => $job->published_at ?? now(),
                'closed_at' => null,
            ])->save();

            return true;
        }

        // لا سعة مجّانية — نستهلك رصيداً مدفوعاً إن وُجد.
        $credit = $this->unusedPaidSlots($company)->first();

        if ($credit === null) {
            return false;
        }

        $job->forceFill([
            'status' => JobStatus::Open,
            'published_at' => $job->published_at ?? now(),
            'closed_at' => null,
            'is_paid_slot' => true,
            'payment_id' => $credit->id,
        ])->save();

        return true;
    }

    public function openCount(CompanyProfile $company): int
    {
        return JobPosting::query()
            ->where('company_profile_id', $company->id)
            ->where('status', JobStatus::Open)
            ->count();
    }

    /** هل تملك الشركة خانةً تفتح فيها وظيفةً أخرى؟ */
    public function hasFreeSlot(CompanyProfile $company): bool
    {
        return $this->summary($company)['remaining'] > 0;
    }

    /**
     * شراء خانة إضافية.
     *
     * تُنشَأ دفعةٌ `pending` بـ `purpose = job_slot`. الخانة **لا تُمنَح هنا**:
     * تُمنَح في الـ webhook بعد أن يستقرّ المال (`grantPurchasedSlot`) — تماماً
     * كالكشف. لا خانةَ بلا دفع، ولا دفعَ بلا خانة.
     *
     * @return array{payment: Payment, payment_url: string}
     */
    public function purchaseSlot(CompanyProfile $company, string $idempotencyKey): array
    {
        // إعادة إرسال الطلب نفسه لا تُنشئ دفعةً ثانية.
        $replay = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($replay !== null) {
            return [
                'payment' => $replay,
                'payment_url' => $this->gateway->createIntent($replay)->paymentUrl,
            ];
        }

        $fee = (int) settings(SettingKeys::JOB_EXTRA_SLOT_FEE, 0);
        $currency = (string) settings(SettingKeys::BILLING_CURRENCY, 'QAR');

        return DB::transaction(function () use ($company, $idempotencyKey, $fee, $currency): array {
            $payment = Payment::create([
                'company_profile_id' => $company->id,
                'purpose' => PaymentPurpose::JobSlot,
                'provider' => $this->gateway->name(),
                'provider_ref' => 'pending_'.$idempotencyKey,
                'idempotency_key' => $idempotencyKey,

                // ADR-006: السعر يُجمَّد هنا. رفعُ المشرف للسعر غداً لا يمسّ
                // هذه الفاتورة.
                'amount' => $fee,
                'currency' => $currency,
                'status' => PaymentStatus::Pending,
            ]);

            $intent = $this->gateway->createIntent($payment);

            $payment->update(['provider_ref' => $intent->providerRef]);

            return ['payment' => $payment, 'payment_url' => $intent->paymentUrl];
        });
    }

    /**
     * ★ يُستدعى من webhook الدفع فقط. لا متحكّم يمنح خانةً.
     *
     * لا نُنشئ صفّاً هنا: الخانة رصيدٌ لا كائن. `summary()` تعدّ الوظائف
     * المعلَّمة `is_paid_slot`، فمنحُ الخانة يقع حين تُفتح الوظيفة التالية
     * وتُنسَب إلى هذه الدفعة (`consumePurchasedSlot`).
     *
     * فإن أغلقت الشركةُ التبويب بعد الدفع ولم تفتح وظيفة، بقي رصيدُها محفوظاً
     * في الدفعة — لا يضيع.
     */
    public function markPaid(Payment $payment): void
    {
        $this->audit->log(AuditLogger::JOB_SLOT_PURCHASED, $payment, [
            'company_profile_id' => $payment->company_profile_id,
            'amount' => $payment->amount,
        ]);
    }

    /**
     * دفعاتُ خاناتٍ استقرّ مالُها ولم تُستهلك في وظيفة بعد.
     *
     * الشركة قد تدفع ثم تُغلق التبويب. حين تعود وتفتح وظيفة، نستهلك رصيدها
     * بدل مطالبتها بالدفع مرّتين.
     *
     * @return Collection<int, Payment>
     */
    private function unusedPaidSlots(CompanyProfile $company): Collection
    {
        return Payment::query()
            ->where('company_profile_id', $company->id)
            ->where('purpose', PaymentPurpose::JobSlot)
            ->where('status', PaymentStatus::Paid)
            ->whereDoesntHave('jobPosting')
            ->orderBy('id')
            ->get();
    }
}
