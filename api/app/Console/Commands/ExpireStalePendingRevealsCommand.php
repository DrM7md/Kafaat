<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\RevealStatus;
use App\Models\CandidateReveal;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * يُفشِل الكشوف المعلّقة التي هُجرت دفعاتها.
 *
 * ★ لمَ هذا لازم: العمود المولَّد `active_reveal_key` يعدّ `pending` خانةً
 *   مشغولة للزوج (شركة، موظّف). شركةٌ ضغطت «كشف» ثم أغلقت صفحة الدفع تترك
 *   كشفاً معلّقاً إلى الأبد — ولا webhook يأتي ليُنهيه. فتُحرَم من ذلك
 *   المرشّح دائماً برسالة «طلبٌ آخر قيد المعالجة».
 *
 *   بلا هذه المهمّة، فشلُ الدفع الصامت = حجبٌ أبديّ لا يُفكّ إلّا بتدخّل
 *   في قاعدة البيانات.
 */
final class ExpireStalePendingRevealsCommand extends Command
{
    protected $signature = 'kafaat:expire-stale-reveals {--minutes=60 : عمر الكشف المعلّق قبل إفشاله}';

    protected $description = 'يُفشِل الكشوف المعلّقة المهجورة فيُحرّر خانة الزوج (شركة، موظّف).';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $cutoff = now()->subMinutes($minutes);

        $stale = CandidateReveal::query()
            ->where('status', RevealStatus::Pending)
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        if ($stale->isEmpty()) {
            $this->info('لا كشوف معلّقة مهجورة.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($stale): void {
            // الدفعة المعلّقة تُفشَل معه. الدفعة المدفوعة **لا تُمسّ**:
            // كشفٌ معلّق بدفعةٍ مدفوعة عطبٌ يحتاج بشراً، لا مهمّةً مجدولة.
            $paymentIds = CandidateReveal::query()
                ->whereIn('id', $stale)
                ->whereNotNull('payment_id')
                ->pluck('payment_id');

            Payment::query()
                ->whereIn('id', $paymentIds)
                ->where('status', PaymentStatus::Pending)
                ->update(['status' => PaymentStatus::Failed]);

            CandidateReveal::query()
                ->whereIn('id', $stale)
                ->where('status', RevealStatus::Pending)
                ->update(['status' => RevealStatus::Failed]);
        });

        $this->info("أُفشِل {$stale->count()} كشفاً معلّقاً أقدم من {$minutes} دقيقة.");

        return self::SUCCESS;
    }
}
