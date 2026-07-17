<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Lookup;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * تعديل بيانات الحساب — ما يملكه المستخدم عن نفسه.
 *
 * ما ليس هنا مقصود: `type` و`status` والصلاحيات وحالة التوثيق. تلك يملكها
 * الخادم والمشرف — ولو قبِلناها في الجسم لصار ترقيةَ نفسِه فعلاً بطلبٍ واحد.
 *
 * ★ حقول الشركة تُطلب فقط ممّن له ملفّ شركة. حساب الموظّف لو أرسلها فلن
 *   تُكتب أصلاً (المتحكّم لا يمسّ إلّا `companyProfile` الموجود).
 */
final class UpdateAccountRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->user();

        $isCompany = $user->companyProfile !== null;

        return [
            'name' => ['required', 'string', 'max:120'],

            // البريد هو مُعرّف الدخول: فريدٌ بين الجميع، إلّا عن صاحبه نفسه —
            // وإلّا لأخفق حفظُ نموذجٍ لم يُغيَّر فيه البريد أصلاً.
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],

            // ── بيانات الشركة ──
            'legal_name' => [Rule::requiredIf($isCompany), 'string', 'max:160'],
            'brand_name' => ['nullable', 'string', 'max:160'],

            // ★ رقم السجلّ التجاري **إجباري**: هو ما يُوثَّق به وجودُ الشركة،
            //   وبلا سجلٍّ لا يملك المشرف ما يتحقّق منه — فيصير التوثيق ختماً
            //   على فراغ. مطلوبٌ عند التسجيل وعند كل حفظٍ للحساب.
            'cr_number' => [Rule::requiredIf($isCompany), 'string', 'max:40'],

            'industry_id' => ['nullable', Rule::exists('lookups', 'id')
                ->where('group', Lookup::INDUSTRY)
                ->where('status', Lookup::STATUS_APPROVED)],

            'size_id' => ['nullable', Rule::exists('lookups', 'id')
                ->where('group', Lookup::COMPANY_SIZE)
                ->where('status', Lookup::STATUS_APPROVED)],
        ];
    }

    /**
     * المشروع بلا ملفّات ترجمة (`app.locale = en`)، والواجهة تعرض `message`
     * كما يصل — فبلا هذا يرى مستخدمٌ عربيّ رسالةً إنجليزية.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'email.unique' => 'هذا البريد مسجَّل لحسابٍ آخر.',
            'legal_name.required' => 'الاسم القانوني للشركة مطلوب.',
            'cr_number.required' => 'رقم السجلّ التجاري مطلوب.',
        ];
    }
}
