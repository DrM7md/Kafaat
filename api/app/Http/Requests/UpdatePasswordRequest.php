<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * تغيير كلمة المرور.
 *
 * ★ `current_password` لازمة ولو كان المستخدم مصادَقاً: جلسةٌ مسروقة (جهازٌ
 *   تُرك مفتوحاً) تكفي لتغيير كلمة المرور وقفل صاحبها خارج حسابه. معرفةُ
 *   القديمة هي الفارق بين «مَن يجلس أمام الشاشة» و«مَن يملك الحساب».
 */
final class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'أدخل كلمة المرور الحالية.',
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
            'password.required' => 'أدخل كلمة المرور الجديدة.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
            'password.different' => 'الكلمة الجديدة هي نفسها القديمة.',
        ];
    }
}
