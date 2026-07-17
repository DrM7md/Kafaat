<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ما الذي تشتريه هذه الدفعة.
 *
 * ★ آلة الدفع بُنيت للكشف حصراً: الـ webhook كان يجد الكشفَ بـ `payment_id`
 *   ويرمي إن لم يجده. فدفعةٌ لخانة وظيفة كانت تنهار **بعد** أن يدخل المال.
 *
 *   هذا الـ enum هو ما يجعل الـ webhook يعرف ماذا يُتمّ. أي غرضٍ جديد يُضاف
 *   هنا، ويُوصَل في `PaymentWebhookController` — ولا يُمَسّ ما قبله.
 */
enum PaymentPurpose: string
{
    /** رسم كشف هوية مرشّح. */
    case Reveal = 'reveal';

    /** خانة وظيفة إضافية تتجاوز الحصّة المجّانية. */
    case JobSlot = 'job_slot';

    public function label(): string
    {
        return match ($this) {
            self::Reveal => 'كشف هوية',
            self::JobSlot => 'خانة وظيفة إضافية',
        };
    }
}
