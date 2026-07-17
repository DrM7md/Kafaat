<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'legal_name' => ['required', 'string', 'max:160'],

            // ★ إجباري: هو ما يُوثَّق به وجود الشركة. بلا سجلٍّ لا يملك المشرف
            //   ما يتحقّق منه، فيصير التوثيق ختماً على فراغ.
            'cr_number' => ['required', 'string', 'max:40'],
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
        ];
    }

    /**
     * رسائل عربية للحقول التي يُخطئ فيها المسجِّل عادةً.
     *
     * المشروع بلا ملفّات ترجمة (`app.locale = en`)، والواجهة تعرض `message`
     * كما يصل — فبلا هذا يرى مستخدمٌ عربيّ «The cr number field is required.»
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'legal_name.required' => 'الاسم القانوني للشركة مطلوب.',
            'cr_number.required' => 'رقم السجلّ التجاري مطلوب.',
            'email.unique' => 'هذا البريد مسجَّل بالفعل.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
        ];
    }
}
