<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use App\Support\SettingKeys;
use Illuminate\Notifications\Notification;

/**
 * الموضع الوحيد الذي تُرسَل منه الإشعارات.
 *
 * ★ التبليغ بالصلاحية لا بالدور: «أبلِغ من يستطيع اعتماد الكفاءات» لا «أبلِغ
 *   مشرف المحتوى». ADR-007 — لا يُذكر اسم دورٍ خارج `Gate::before`.
 *
 * ★★ والصلاحية تُسأل عنها الـ Gate لا `getAllPermissions()`: المشرف العام
 *    **لا تُسنَد إليه صلاحيةٌ صراحةً** — يمنحه `Gate::before` كل شيء. فاستعمال
 *    `User::permission(...)` من Spatie كان سيُسقطه من كل إشعار، وهو عين
 *    الخطأ الذي كلّف لوحةً فارغة من قبل (ADR-018).
 */
final class Notifier
{
    /** يُبلِغ مستخدماً بعينه. */
    public function send(?User $user, Notification $notification): void
    {
        if ($user === null || ! $this->enabled()) {
            return;
        }

        $user->notify($notification);
    }

    /**
     * يُبلِغ كل من يملك صلاحيةً — أياً كان طريقه إليها: دورٌ، أو استثناءٌ
     * فردي، أو `Gate::before`.
     */
    public function sendToPermission(string $permission, Notification $notification): void
    {
        if (! $this->enabled()) {
            return;
        }

        foreach (User::whoCan($permission) as $user) {
            $user->notify($notification);
        }
    }

    /** المفتاح الإداري يُطفئ الإشعارات كلّها بنقرة — بلا نشر كود. */
    private function enabled(): bool
    {
        return (bool) settings(SettingKeys::FEATURE_NOTIFICATIONS, true);
    }
}
