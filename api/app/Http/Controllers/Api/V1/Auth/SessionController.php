<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * ADR-002: جلسة كوكيز httpOnly. لا توكن يُعاد للواجهة، ولا يُخزَّن في localStorage.
 */
final class SessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // ★ موازنة التوقيت: Auth::attempt يتخطّى حساب الهاش حين لا يوجد
            //   المستخدم، فيُفرّق زمنُ الردّ بين بريدٍ مسجَّل وآخر غير مسجَّل —
            //   أوراكل وجودٍ عبر الساعة. نحرق زمن bcrypt مكافئاً عند الغياب.
            if (! User::query()->where('email', $credentials['email'])->exists()) {
                Hash::make($credentials['password']);
            }

            // أثرٌ للتحقيق وكشف رشّ كلمات المرور — بلا كلمة المرور نفسها.
            Log::warning('auth.login_failed', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'بيانات الاعتماد غير صحيحة.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->status !== UserStatus::Active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'هذا الحساب '.$user->status->label().'.',
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return MeResource::make($user)->response();
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        // طلبٌ بتوكن (الجوال مستقبلاً) لا جلسة له. لا نفترض وجودها.
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }
}
