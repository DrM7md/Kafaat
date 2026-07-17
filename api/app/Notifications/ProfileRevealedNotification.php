<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * يُعلَم الموظّف بأن شركةً اطّلعت على ملفّه.
 *
 * ⚠️ لا يُذكر أي شيء يمسّ سرّيته تجاه صاحب عمله. واسم الشركة الكاشفة
 * يُذكر لأنها هي من بادر — لا العكس.
 */
final class ProfileRevealedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $companyName) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * نفس شكل `PlatformNotification` — صندوقُ الإشعارات يقرأ شكلاً واحداً.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'key' => 'profile.revealed',
            'title' => 'اطّلعت شركةٌ على ملفّك',
            'body' => "كشفت «{$this->companyName}» بيانات التواصل معك. قد تتلقّى اتصالاً منها قريباً.",
            'link' => '/profile',
            'meta' => ['company' => $this->companyName],
        ];
    }
}
