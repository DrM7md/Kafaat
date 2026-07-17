<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * إشعار المنصّة — صنفٌ واحد لكل الأحداث.
 *
 * ★★ ⚠️ **لا يُكتب اسم مرشّحٍ في `data` أبداً.**
 *
 *    الإشعار صفٌّ دائم في القاعدة، والحجب حالةٌ متغيّرة: الاسترداد **يُعيد
 *    الحجب** (`refund.reblocks_access`). فلو خزّنّا اسم المرشّح في إشعار
 *    «تمّ الكشف» لبقي الاسم مقروءاً في صندوق الشركة بعد استرداد مالها وفقدِ
 *    وصولها — تسريبٌ دائم لا يُصلحه أي حارس لاحق.
 *
 *    نُخزّن `ref` ورابطاً. والاسم يُقرأ من الملفّ وقت الفتح، فيمرّ بالحجب.
 *
 *    اسم **الشركة** في إشعار الموظّف مستثنى: هي من بادر بالكشف، فلا سرّ لها
 *    تجاهه. العكس ليس صحيحاً.
 */
final class PlatformNotification extends Notification
{
    use Queueable;

    /**
     * @param  string  $key  معرّف نوع الحدث — تُميّز به الواجهةُ الأيقونة واللون
     * @param  string|null  $link  مسار الواجهة الذي يفتحه النقر
     * @param  array<string, scalar|null>  $meta
     */
    public function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly string $body,
        private readonly ?string $link = null,
        private readonly array $meta = [],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'body' => $this->body,
            'link' => $this->link,
            'meta' => $this->meta,
        ];
    }
}
