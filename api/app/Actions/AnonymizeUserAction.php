<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\Attachment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ADR-012 — حقّ الحذف يتصادم مع الالتزام المحاسبي بحفظ سجلّ المعاملات.
 *
 * الحلّ: إخفاء الهوية لا `DELETE`.
 *   • الحقول المعرِّفة تُصفَّر.
 *   • المرفقات تُحذف فعلياً من التخزين.
 *   • الصفوف المالية وسجلّ التدقيق تبقى — بلا ما يعرّف صاحبها.
 *
 * يُفصَح عن هذا صراحةً في سياسة الخصوصية.
 */
final class AnonymizeUserAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->purgeAttachments($user);

            $user->forceFill([
                'name' => 'مستخدم محذوف',
                'email' => 'deleted-'.Str::uuid7().'@kafaat.invalid',
                'phone' => null,
                'password' => Str::random(64),
                'status' => UserStatus::Suspended,
                'remember_token' => null,
            ])->save();

            $user->employeeProfile?->forceFill([
                'summary' => null,
                'notice_period_clause' => null,
                'current_employer_name' => null,
                'current_employer_company_id' => null,
                'visibility' => 'hidden',
                'published_at' => null,
            ])->save();

            $user->employeeProfile?->experiences()->delete();
            $user->employeeProfile?->educations()->delete();
            $user->employeeProfile?->delete();   // حذف ناعم
            $user->companyProfile?->delete();

            // يبقى actor_id في audit_logs: المساءلة تتطلّبه (ADR-011).
            $this->audit->log(AuditLogger::ACCOUNT_ANONYMIZED, $user, [
                'anonymized_at' => now()->toIso8601String(),
            ]);

            $user->tokens()->delete();
            $user->delete();   // حذف ناعم — نزاهة الفواتير محفوظة
        });
    }

    /** المرفقات تُمحى فعلياً: لا يبقى لها أثر على القرص. */
    private function purgeAttachments(User $user): void
    {
        $profile = $user->employeeProfile;

        if ($profile === null) {
            return;
        }

        $profile->attachments->each(function (Attachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        });
    }
}
