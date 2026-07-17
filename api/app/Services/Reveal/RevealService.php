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
use App\Services\Audit\AuditLogger;
use App\Services\Masking\ProfileVisibilityService;
use App\Services\Notifications\Events;
use App\Services\Notifications\Notifier;
use App\Services\Payments\PaymentGateway;
use App\Support\Permissions;
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
        private readonly RevealPricing $pricing,
        private readonly Notifier $notifier,
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
        // السعر من مجال الملفّ لا من إعدادٍ واحد للجميع — ثمّ يُجمَّد أدناه.
        $fee = $this->pricing->forProfile($profile);
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
     * خصم رصيد الاشتراك — **غير منفَّذ بعد** (features.subscriptions = false).
     *
     * لا معاملةَ ولا قفلَ هنا: لا شيء يُقرأ ولا يُكتب. كانا يوهمان القارئ
     * بمنطقٍ قائم. حين يصل جدول الأرصدة يعود `lockForUpdate()` — وبلا قفلٍ
     * يقرأ طلبان متزامنان الرصيد نفسه فيخصمان مرّتين من رصيدٍ يكفي واحداً.
     * (ولهذا نختبر على MySQL: SQLite تتجاهل القفل بصمت — ADR-015.)
     */
    private function revealBySubscription(CompanyProfile $company): RevealResult
    {
        throw new InsufficientCreditsException($company->id);
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

            // استقرّ المال ⇒ لا أثر ثانياً. البوّابات تُعيد إرسال الأحداث
            // وتخلط ترتيبها؛ بلا هذا الحارس يتكرّر صفّ التدقيق ويصل الموظّف
            // إشعارٌ ثانٍ بأن شركةً اطّلعت على ملفّه.
            if ($payment === null || $payment->status->isSettled()) {
                return null;
            }

            // forceFill للطوابع الزمنية: تُكتب من النظام لا من مدخلات المستخدم،
            // فلا تُدرَج في fillable.
            $payment->forceFill(['status' => PaymentStatus::Paid, 'paid_at' => now()])->save();

            $reveal = CandidateReveal::query()->where('payment_id', $payment->id)->firstOrFail();

            // الدفعة كانت `failed` وعادت `paid` (تحقّقٌ متأخّر). إن كانت الشركة
            // قد بدأت كشفاً جديداً لنفس المرشّح فالخانة مشغولة، ويصطدم الفهرس
            // الفريد. المال دخل: لا نُسقطه ولا ننهار — نُصعّده للمراجعة اليدوية.
            try {
                $reveal->forceFill([
                    'status' => RevealStatus::Completed,
                    'revealed_at' => now(),
                ])->save();
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }

                $this->audit->log(AuditLogger::PAYMENT_RECONCILIATION_REQUIRED, $payment, [
                    'reveal_id' => $reveal->id,
                    'reason' => 'دفعةٌ نجحت بعد فشلها، وللزوج (شركة، موظّف) كشفٌ نشط آخر.',
                ]);

                // ★★ صفٌّ في سجلٍّ لا يقرؤه أحد ليس تنبيهاً. مالُ عميلٍ معلَّق
                //    ولا كشفَ يقابله — يحتاج إنساناً الآن، لا عند المراجعة.
                $this->notifier->sendToPermission(
                    Permissions::PAYMENT_VIEW,
                    Events::reconciliationRequired($payment->provider_ref),
                );

                return null;
            }

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
     * ★ فشل الدفع ⇒ يفشل الكشف معه. يُستدعى من الـ webhook فقط.
     *
     * كان الفشل يمسّ صفّ الدفعة وحده، فيبقى الكشف `pending` إلى الأبد.
     * والعمود المولَّد `active_reveal_key` يعدّ `pending` خانةً مشغولة،
     * فيرفض الفهرس الفريد أي محاولةٍ لاحقة: **الشركة تُحرَم من هذا المرشّح
     * للأبد** برسالة «طلبٌ آخر قيد المعالجة». `failed` تُخلي الخانة.
     */
    public function failFromPayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $payment = Payment::query()->lockForUpdate()->find($payment->id);

            // دفعةٌ استقرّت لا تُخفَّض. حدث فشلٍ مُعاد بعد النجاح كان يُرجعها
            // إلى `failed`، فيجتاز حدثُ نجاحٍ مُعادٌ بعده حارسَ التكرار.
            if ($payment === null || $payment->status !== PaymentStatus::Pending) {
                return;
            }

            $payment->forceFill(['status' => PaymentStatus::Failed])->save();

            CandidateReveal::query()
                ->where('payment_id', $payment->id)
                ->where('status', RevealStatus::Pending)
                ->update(['status' => RevealStatus::Failed]);
        });
    }

    /**
     * ADR-010: الاسترداد يُعيد الحجب فوراً. إبقاء الوصول بعد ردّ المال
     * خدمةٌ بلا مقابل — خطأ منطقي وقانوني.
     *
     * ⚠️ القفل والحارس ليسا زينة: نداءان متزامنان (أو زرٌّ مضغوط مرّتين)
     *    كانا يُصدران استرداداً مزدوجاً من البوّابة — مالٌ يخرج مرّتين
     *    مقابل فاتورةٍ واحدة. `RefundNotAllowedException` تُوقف الثاني.
     *
     * @throws RefundNotAllowedException
     */
    public function refund(CandidateReveal $reveal): void
    {
        DB::transaction(function () use ($reveal): void {
            /** @var CandidateReveal|null $locked */
            $locked = CandidateReveal::query()->lockForUpdate()->find($reveal->id);

            if ($locked === null || $locked->status !== RevealStatus::Completed) {
                throw new RefundNotAllowedException($reveal->id);
            }

            $percentage = (int) settings(SettingKeys::REFUND_PERCENTAGE, 100);
            $amount = (int) round($locked->amount * $percentage / 100);

            $payment = $locked->payment;

            if ($payment !== null) {
                if ($payment->status !== PaymentStatus::Paid) {
                    throw new RefundNotAllowedException($reveal->id);
                }

                $this->gateway->refund($payment, $amount);

                $payment->forceFill([
                    'status' => PaymentStatus::Refunded,
                    'refunded_at' => now(),
                ])->save();
            }

            if ((bool) settings(SettingKeys::REFUND_REBLOCKS_ACCESS, true)) {
                // بتحوّل الحالة إلى `refunded` يصبح العمود المولَّد NULL،
                // فتتحرّر الخانة ويصبح كشفٌ جديد مدفوع ممكناً.
                $locked->forceFill(['status' => RevealStatus::Refunded, 'refunded_at' => now()])->save();
            }

            $this->audit->log(AuditLogger::PAYMENT_REFUNDED, $locked, [
                'amount' => $amount,
                'percentage' => $percentage,
            ]);
        });

        // خارج المعاملة عمداً: إشعارٌ عن استردادٍ تراجَع (rollback) أسوأ من
        // لا إشعار. والصمت هنا يعني شركةً تظنّ أنّ لها وصولاً وقد أُعيد حجبه.
        $this->notifier->send(
            $reveal->companyProfile->user,
            Events::revealRefunded($reveal->employeeProfile->ref),
        );
    }

    private function notifyEmployee(CandidateReveal $reveal): void
    {
        // مفتاحٌ خاصّ بهذا الإشعار: للموظّف أن يُعلَم بمن كشفه أو لا يُعلَم،
        // وهو قرار خصوصيّة مستقلّ عن تعطيل الإشعارات كلّها.
        if (! (bool) settings(SettingKeys::PRIVACY_NOTIFY_ON_REVEAL, true)) {
            return;
        }

        $this->notifier->send(
            $reveal->employeeProfile->user,
            Events::profileRevealed($reveal->companyProfile->legal_name),
        );

        // ★ والشركةُ أيضاً: الكشف غير متزامن (202 ثم webhook). بلا هذا لا
        //   تعرف متى صار بوسعها رؤية بيانات التواصل، فتظلّ تُحدّث الصفحة.
        $this->notifier->send(
            $reveal->companyProfile->user,
            Events::revealCompleted($reveal->employeeProfile->ref),
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
