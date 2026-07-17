<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;

/**
 * صندوق إشعارات المستخدم.
 *
 * ★ لا سياسة ولا حارس صلاحية هنا — ولا حاجة: كل استعلامٍ مقيّدٌ بعلاقة
 *   `$user->notifications()`. لا يمكن لمستخدمٍ أن يصل إشعار غيره، لأن
 *   الاستعلام لا يخرج أصلاً من صفوفه.
 *
 * «تمّ» = `read_at`. المستخدم يفتح الإشعار فيُعلَّم منجَزاً — وتبويبةُ «تمّ»
 * تُريه ما تابعه بالفعل.
 */
final class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'new', 'done'])],
        ]);

        /** @var User $user */
        $user = $request->user();

        // `all` هو الافتراضي: صندوق الموظّف والشركة قائمةٌ واحدة — يقرأ، يضغط،
        // ينتهي. تبويبتا «جديد/تمّ» للمشرف وحده، لأنّ إشعاراته **طابور عمل**
        // لا خبر: يحتاج أن يعرف ما تابعه وما بقي.
        $filter = $data['filter'] ?? 'all';

        $query = $user->notifications()
            ->when($filter === 'new', fn ($q) => $q->whereNull('read_at'))
            ->when($filter === 'done', fn ($q) => $q->whereNotNull('read_at'));

        $page = $query->paginate(self::PER_PAGE);

        return response()->json([
            'data' => array_map(
                fn (DatabaseNotification $n): array => $this->row($n),
                $page->items(),
            ),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],

            // العدّاد للجرس — يُحسب دائماً، أياً كانت التبويبة المفتوحة.
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /** تعليمُ إشعارٍ منجَزاً. النقر عليه في الواجهة يستدعي هذا ثم ينتقل لرابطه. */
    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // `findOrFail` على العلاقة لا على الجدول: إشعار غيره لا يُوجَد أصلاً
        // في نطاق هذا الاستعلام، فيعود 404 لا 403.
        $notification = $user->notifications()->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'notification' => $this->row($notification->fresh()),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'key' => $data['key'] ?? 'unknown',
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'link' => $data['link'] ?? null,
            'meta' => $data['meta'] ?? [],
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
