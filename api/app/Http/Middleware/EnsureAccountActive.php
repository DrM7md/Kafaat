<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * حسابٌ عُلِّق أو لم يُفعَّل لا يُكمل جلسته الحالية.
 *
 * فحص الحالة عند الدخول (SessionController) لا يكفي وحده: لو عُلِّق حسابٌ
 * بعد دخوله بقيت جلسته عاملةً حتى انتهائها. هذا الحارس يعيد الفحص كلَّ طلبٍ
 * مصادَق، فيسقط الوصول لحظةَ تغيّر الحالة.
 *
 * ⚠️ يلي auth:sanctum: يحتاج مستخدماً مصادَقاً. لا يزيد صرامةً على الدخول —
 *    الدخول نفسه يرفض غير Active — بل يمدّ القاعدة ذاتها إلى الجلسات القائمة.
 */
final class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null && $user->status !== UserStatus::Active) {
            // نُسقط الجلسة القائمة: حسابٌ معلَّقٌ لا يُترك بمفتاحٍ عاملٍ في يده.
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'message' => 'هذا الحساب '.$user->status->label().'.',
            ], 403);
        }

        return $next($request);
    }
}
