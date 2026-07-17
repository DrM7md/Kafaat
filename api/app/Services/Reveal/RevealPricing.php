<?php

declare(strict_types=1);

namespace App\Services\Reveal;

use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Support\SettingKeys;

/**
 * سعر كشف ملفٍّ بعينه.
 *
 * ★ الموضع **الوحيد** الذي يُحسَب فيه السعر. قبل هذا كان `settings('reveal.fee')`
 *   يُقرأ في مكانين (الـ Resource و`RevealService`)، ولو تباعدا لعرضت الواجهة
 *   سعراً وخصم الخادم غيره.
 *
 * السعر معطى القائمة لا ثابتٌ في الكود: يعيش في `lookups.meta.reveal_fee`
 * للمجال. مجالٌ بلا سعرٍ خاصّ يرث `reveal.fee` من الإعدادات.
 *
 * ⚠️ هذا سعرُ **العرض** فقط. السعر المُلزِم يُجمَّد في `payments.amount` لحظة
 *    إنشاء الفاتورة (ADR-006) — تعديلُ المشرف بعدها لا يمسّ فاتورةً قائمة.
 */
final class RevealPricing
{
    public const META_KEY = 'reveal_fee';

    public function forProfile(EmployeeProfile $profile): int
    {
        // `field` قد تكون محمَّلةً مسبقاً (التصفّح يُحمّلها لكل الصفحة)، فلا
        // نُطلق استعلاماً لكل مرشّح.
        $field = $profile->relationLoaded('field') ? $profile->field : $profile->field()->first();

        return $this->forField($field);
    }

    public function forField(?Lookup $field): int
    {
        $fee = $field?->meta[self::META_KEY] ?? null;

        // القيمة تأتي من JSON فقد تصل نصّاً. و`0` سعرٌ مشروع (كشفٌ مجّاني
        // لمجالٍ تريد تنشيطه)، فلا نعتبره «غير مسعَّر».
        if (is_numeric($fee)) {
            return max(0, (int) $fee);
        }

        return $this->default();
    }

    public function default(): int
    {
        return (int) settings(SettingKeys::REVEAL_FEE, 0);
    }
}
