<?php

declare(strict_types=1);

namespace App\Services\Reveal;

use App\Enums\PaymentStatus;
use App\Enums\RevealSource;
use App\Enums\RevealStatus;
use App\Exceptions\ProfileNotVisibleException;
use App\Models\CandidateReveal;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\Payment;
use App\Notifications\ProfileRevealedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Masking\ProfileVisibilityService;
use App\Services\Payments\PaymentGateway;
use App\Support\SettingKeys;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * ★ أخطر شيفرة في المشروع: مالٌ وتزامن معاً.
 *
 * الضمانات الأربعة (Brain/06 §6):
 *   1. لا خصم مزدوج    — الفهرس الفريد على العمود المولَّد + Idempotency-Key.
 *   2. لا كشف بلا دفع  — `completed` تُكتب في معاملة الـ webhook فقط.
 *   3. لا دفع بلا كشف  — webhook فاشل يُعاد عبر الطابور.
 *   4. لا سباق         — إنشاء الكشف داخل معاملة، والفهرس هو الحكم النهائي.
 */
final class RevealService
{
    public function __construct(
        private readonly ProfileVisibilityService $visibility,
        private readonly PaymentGateway $gateway,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws ProfileNotVisibleException إن كان الطالب صاحب العمل الحالي
     * @throws InsufficientCreditsException
     */
    public function reveal(CompanyProfile $company, EmployeeProfile $profile, string $idempotencyKey): RevealResult
    {
        // ★ عمى صاحب العمل يسبق المال. شركةٌ دفعت لا تستطيع كشف موظّفها
        //   الحالي — هذا ليس شيئاً يُشترى.
        if ($this->visibility->isCurrentEmployer($company->user, $profile)) {
            throw new ProfileNotVisibleException;
        }

        // كشفٌ سابق صالح؟ لا خصم جديد. يُعاد 200 لا 201.
        $existing = CandidateReveal::query()
            ->where('company_profile_id', $company->id)
            ->where('employee_profile_id', $profile->id)
            ->valid()
            ->first();

        if ($existing !== null) {
            return RevealResult::alreadyRevealed($existing);
        }

        // إعادة إرسال الطلب نفسه (نفس المفتاح) لا تُنشئ سجلّاً ثانياً.
        $replay = CandidateReveal::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($replay !== null) {
            return $replay->status->unmasks()
                ? RevealResult::alreadyRevealed($replay)
                : RevealResult::awaitingPayment($replay, $this->resumeUrl($replay));
        }

        return match ((string) settings(SettingKeys::BILLING_MODEL, 'pay_per_reveal')) {
            'subscription' => $this->revealBySubscription($company),
            default => $this->revealByPayment($company, $profile, $idempotencyKey),
        };
    }

    /**
     * ADR-006: السعر يُجمَّد هنا. `settings()` تُقرأ مرّةً واحدة، وتُخزَّن نسخةً
     * في السجلّ. تعديل المشرف للسعر لاحقاً لا يمسّ هذه الفاتورة.
     */
    private function revealByPayment(CompanyProfile $company, EmployeeProfile $profile, string $key): RevealResult
    {
        $fee = (int) settings(SettingKeys::REVEAL_FEE, 0);
        $currency = (string) settings(SettingKeys::BILLING_CURRENCY, 'QAR');

        try {
            [$reveal, $intent] = DB::transaction(function () use ($company, $profile, $key, $fee, $currency) {
                $payment = Payment::create([
                    'company_profile_id' => $company->id,
                    'provider' => $this->gateway->name(),
                    'provider_ref' => 'pending_'.$key,
                    'idempotency_key' => $key,
                    'amount' => $fee,
                    'currency' => $currency,
                    'status' => PaymentStatus::Pending,
                ]);

                $intent = $this->gateway->createIntent($payment);

                $payment->update(['provider_ref' => $intent->providerRef]);

                // `pending` لا تفكّ الحجب. الفهرس المولَّد يحجز الخانة
                // فيمنع طلباً متزامناً من إنشاء دفعةٍ ثانية.
                $reveal = CandidateReveal::create([
                    'company_profile_id' => $company->id,
                    'employee_profile_id' => $profile->id,
                    'payment_id' => $payment->id,
                    'source' => RevealSource::Payment,
                    'amount' => $fee,
                    'currency' => $currency,
                    'status' => RevealStatus::Pending,
                    'idempotency_key' => $key,
                ]);

                return [$reveal, $intent];
            });
        } catch (QueryException $e) {
            // اصطدام الفهرس الفريد = طلبٌ متزامن سبقنا. لا خصم مزدوج.
            if ($this->isUniqueViolation($e)) {
                throw new ConcurrentRevealException;
            }

            throw $e;
        }

        return RevealResult::awaitingPayment($reveal, $intent->paymentUrl);
    }

    /**
     * خصم رصيد الاشتراك.
     *
     * ⚠️ `lockForUpdate()` هنا ليس زينة: بلا قفل الصفّ، طلبان متزامنان
     *    يقرآن الرصيد نفسه ويخصمان مرّتين من رصيدٍ يكفي واحداً.
     *    (ولهذا نختبر على MySQL: SQLite تتجاهل القفل بصمت — ADR-015.)
     */
    private function revealBySubscription(CompanyProfile $company): RevealResult
    {
        return DB::transaction(function () use ($company): RevealResult {
            $locked = CompanyProfile::query()->lockForUpdate()->findOrFail($company->id);

            // الاشتراكات مؤجّلة خارج MVP (features.subscriptions = false).
            // البنية والقفل جاهزان؛ جدول الأرصدة يأتي مع تفعيل الميزة.
            throw new InsufficientCreditsException($locked->id);
        });
    }

    /**
     * ★ يُستدعى من webhook الدفع فقط. لا متحكّم يكتب `completed`.
     *
     * معاملة واحدة: الدفعة مدفوعة + الكشف مكتمل + أثر تدقيق.
     * فشلٌ في أي جزء ⇒ تراجع كامل ⇒ إعادة محاولة من الطابور.
     */
    public function completeFromPayment(Payment $payment): ?CandidateReveal
    {
        return DB::transaction(function () use ($payment): ?CandidateReveal {
            $payment = Payment::query()->lockForUpdate()->find($payment->id);

            if ($payment === null || $payment->status === PaymentStatus::Paid) {
                return null;   // إعادة تشغيل الـ webhook: أثرٌ واحد لا اثنان.
            }

            // forceFill للطوابع الزمنية: تُكتب من النظام لا من مدخلات المستخدم،
            // فلا تُدرَج في fillable.
            $payment->forceFill(['status' => PaymentStatus::Paid, 'paid_at' => now()])->save();

            $reveal = CandidateReveal::query()->where('payment_id', $payment->id)->firstOrFail();

            $reveal->forceFill([
                'status' => RevealStatus::Completed,
                'revealed_at' => now(),
            ])->save();

            $this->audit->log(AuditLogger::CANDIDATE_REVEALED, $reveal->employeeProfile, [
                'company_profile_id' => $reveal->company_profile_id,
                'amount' => $reveal->amount,
                'source' => $reveal->source->value,
            ]);

            $this->notifyEmployee($reveal);

            return $reveal;
        });
    }

    /**
     * ADR-010: الاسترداد يُعيد الحجب فوراً. إبقاء الوصول بعد ردّ المال
     * خدمةٌ بلا مقابل — خطأ منطقي وقانوني.
     */
    public function refund(CandidateReveal $reveal): void
    {
        DB::transaction(function () use ($reveal) {
            $percentage = (int) settings(SettingKeys::REFUND_PERCENTAGE, 100);
            $amount = (int) round($reveal->amount * $percentage / 100);

            if ($reveal->payment !== null) {
                $this->gateway->refund($reveal->payment, $amount);

                $reveal->payment->forceFill([
                    'status' => PaymentStatus::Refunded,
                    'refunded_at' => now(),
                ])->save();
            }

            if ((bool) settings(SettingKeys::REFUND_REBLOCKS_ACCESS, true)) {
                // بتحوّل الحالة إلى `refunded` يصبح العمود المولَّد NULL،
                // فتتحرّر الخانة ويصبح كشفٌ جديد مدفوع ممكناً.
                $reveal->forceFill(['status' => RevealStatus::Refunded, 'refunded_at' => now()])->save();
            }

            $this->audit->log(AuditLogger::PAYMENT_REFUNDED, $reveal, [
                'amount' => $amount,
                'percentage' => $percentage,
            ]);
        });
    }

    private function notifyEmployee(CandidateReveal $reveal): void
    {
        if (! (bool) settings(SettingKeys::PRIVACY_NOTIFY_ON_REVEAL, true)) {
            return;
        }

        if (! (bool) settings(SettingKeys::FEATURE_NOTIFICATIONS, true)) {
            return;
        }

        $reveal->employeeProfile->user->notify(
            new ProfileRevealedNotification($reveal->companyProfile->legal_name)
        );
    }

    private function resumeUrl(CandidateReveal $reveal): string
    {
        return config('app.frontend_url').'/payments/resume?reveal='.$reveal->id;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}
