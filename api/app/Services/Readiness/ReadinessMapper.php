<?php

declare(strict_types=1);

namespace App\Services\Readiness;

use App\Models\Lookup;

/**
 * ★ يحوّل نسبةَ الرغبة في الانتقال (0–100) إلى حالةٍ وظيفية.
 *
 * لماذا نسبةٌ يحرّكها الموظّف، لا قائمةٌ يختار منها؟
 *
 *   «راغب في ترك عمله» **اعترافٌ** لا معلومة: من يخشى أن يبلغ مديرَه لن
 *   يختارها ولو كان يبحث منذ سنة، ومن لا يخشى سيختارها ولو كان مرتاحاً —
 *   لأنها تجلب انتباه الشركات. فالخانةُ تمتلئ بلا أن تحمل خبراً.
 *
 *   الشريط يسأل سؤالاً مختلفاً: «كم؟» لا «هل؟». والتدرّج يُقال بصدقٍ لا
 *   يُقال به الاسم الصريح — ويملك صاحبُه ضبطَه في أي لحظة.
 *
 * ★★ العتبات تعيش في `lookups.meta.readiness_min` لا في الكود.
 *
 *    فتُضاف حالةٌ رابعة («يبحث بجدّية» عند 85) بلونها وعتبتها من لوحة
 *    المشرف، بلا سطرٍ برمجيّ ولا نشر. Brain/05-settings-engine.md §4 —
 *    وهو العهد نفسه الذي قُطع لألوان الشارات.
 */
final class ReadinessMapper
{
    /**
     * أعلى حالةٍ عتبتُها لا تتجاوز النسبة.
     *
     * مثال بالبذرة (مستقرّ 0 · منفتح 35 · راغب 70):
     *   12 ⇒ مستقرّ   ·   50 ⇒ منفتح على فرص أفضل   ·   90 ⇒ راغب في ترك عمله
     *
     * تُعيد `null` إن لم تُسعَّر أيّ حالةٍ بعتبة — فلا نلمس حالةَ الموظّف
     * بتخمين. الصمت خيرٌ من إسناد حالةٍ لم يقصدها أحد.
     */
    public function statusFor(int $readiness): ?Lookup
    {
        $tiers = Lookup::query()
            ->where('group', Lookup::EMPLOYMENT_STATUS)
            ->approved()
            ->get()
            ->filter(static fn (Lookup $status): bool => self::thresholdOf($status) !== null)
            ->sortByDesc(static fn (Lookup $status): int => (int) self::thresholdOf($status));

        if ($tiers->isEmpty()) {
            return null;
        }

        // أوّلُ ما تنزل عنده النسبة — والقائمة مرتّبةٌ تنازلياً، فأوّلُ مطابقٍ
        // هو الأعلى. ولو لم تبلغ النسبةُ أدنى عتبة، فأدنى الحالات هي حالُه:
        // مشرفٌ رفع عتبة «مستقرّ» فوق الصفر لا يجوز أن يترك أحداً بلا حالة.
        return $tiers->first(
            static fn (Lookup $status): bool => (int) self::thresholdOf($status) <= $readiness,
        ) ?? $tiers->last();
    }

    /** عتبة الحالة، أو `null` إن لم تُسعَّر. */
    private static function thresholdOf(Lookup $status): ?int
    {
        $min = $status->meta['readiness_min'] ?? null;

        return is_numeric($min) ? (int) $min : null;
    }
}
