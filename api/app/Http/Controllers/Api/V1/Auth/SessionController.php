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
