<?php

declare(strict_types=1);

namespace App\Services\Masking;

use App\Exceptions\ProfileNotVisibleException;
use App\Models\CandidateReveal;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Permissions;
use App\Support\SettingKeys;

/**
 * ★ مصدر الحقيقة الوحيد لسؤال: أي حقولٍ يرى هذا المشاهد من هذا الملفّ؟
 *
 * Brain/06-masking-and-reveal.md §4.
 *
 * لا يجوز لأي متحكّم أو Resource أن يقرّر الحجب بنفسه. كلّهم يسألون هنا.
 */
final class ProfileVisibilityService
{
    /**
     * ذاكرة الطلب الواحد: "{company_id}:{profile_id}" ⇒ أللشركة كشفٌ صالح؟
     *
     * الخدمة مسجَّلة `scoped` (AppServiceProvider)، فالذاكرة تحيا مع الطلب
     * وتموت معه. بلا هذا يُستعلَم عن الكشف مرّةً لكل مرشّح في الصفحة.
     *
     * @var array<string, bool>
     */
    private array $revealMemo = [];

    /**
     * @throws ProfileNotVisibleException إن كان المشاهد صاحب العمل الحالي
     */
    public function visibleFields(?User $viewer, EmployeeProfile $profile): FieldSet
    {
        // 1) صاحب الملفّ يرى كل شيء.
        if ($viewer !== null && $viewer->id === $profile->user_id) {
            return FieldSet::all();
        }

        // 2) صلاحية الإشراف تكشف كل شيء (Brain/04 §3).
        //
        //    التسجيل في التدقيق **ليس هنا**: هذه الدالة تُستدعى مرّةً لكل
        //    مرشّح في القائمة، ومرّتين في المعاينة الواحدة (المتحكّم ثم
        //    الـ Resource). أخطر فعلٍ غير مالي كان يغرق في ضجيجٍ من صنعه.
        //    المتحكّم هو من يُسجّل، مرّةً واحدة لكل طلب. راجع
        //    CandidateController::show() و::index().
        if ($viewer?->can(Permissions::PROFILE_MODERATE) === true) {
            return FieldSet::all();
        }

        // 3) عمى صاحب العمل — يسبق كل شيء آخر، حتى الكشف المدفوع.
        //    شركةٌ دفعت لا تستطيع كشف موظّفها الحالي: المال لا يشتري هذا.
        if ($this->isCurrentEmployer($viewer, $profile)) {
            throw new ProfileNotVisibleException;
        }

        // 4) كشفٌ صالح لهذا الزوج تحديداً؟
        if ($this->hasValidReveal($this->companyOf($viewer), $profile)) {
            return FieldSet::all();
        }

        // 5) الافتراضي: محجوب. قائمة الحجب من الإعدادات لا من الكود.
        //
        // ⚠️ الافتراضي عند **غياب** المفتاح هو القائمة الكاملة، لا `[]`.
        //    `[]` تعني «لا تحجب شيئاً»: صفٌّ مفقود أو كاشٌ بائت كان يكفي
        //    لكشف كل اسمٍ وهاتفٍ وبريد بلا دفع. العطب يُغلق الباب.
        //
        //    أمّا `[]` المكتوبة صراحةً من اللوحة (بتأكيدٍ مزدوج) فتُحترَم:
        //    `settings()` تُميّز المفتاح الغائب من المفتاح الفارغ.
        /** @var list<string> $masked */
        $masked = (array) settings(SettingKeys::MASKING_FIELDS, SettingKeys::defaultMaskedFields());

        return FieldSet::allExcept($masked);
    }

    public function isCurrentEmployer(?User $viewer, EmployeeProfile $profile): bool
    {
        if (! (bool) settings(SettingKeys::PRIVACY_EMPLOYER_BLIND, true)) {
            return false;
        }

        $company = $this->companyOf($viewer);

        return $company !== null
            && $profile->current_employer_company_id === $company->id;
    }

    public function hasValidReveal(?CompanyProfile $company, EmployeeProfile $profile): bool
    {
        if ($company === null) {
            return false;
        }

        $key = $company->id.':'.$profile->id;

        // ★ نقرأ من الذاكرة، ولا نكتب فيها.
        //
        //   الكتابة هنا كانت تُسمّم الذاكرة: طلبٌ يسأل «أمكشوف؟» فيُخزَّن
        //   `false`، ثم يُكمَل الكشف في الطلب نفسه، فيبقى الجواب `false`
        //   إلى أن تموت النسخة. الحجب يبقى بعد الدفع.
        //
        //   `primeReveals()` وحدها تكتب — وتمسح قبل أن تكتب. فالذاكرة لا
        //   تحيا إلّا داخل القراءة التي هيّأتها.
        if (array_key_exists($key, $this->revealMemo)) {
            return $this->revealMemo[$key];
        }

        return CandidateReveal::query()
            ->where('company_profile_id', $company->id)
            ->where('employee_profile_id', $profile->id)
            ->valid()
            ->exists();
    }

    /**
     * يجلب كشوف الشركة الصالحة لدفعةٍ من الملفّات في استعلامٍ واحد.
     *
     * يُستدعى قبل بناء مجموعة الـ Resource: بلا ذلك يسأل كل مرشّحٍ عن نفسه،
     * فصفحةٌ من ٥٠ مرشّحاً = ٥٠ استعلاماً.
     *
     * @param  iterable<EmployeeProfile>  $profiles
     */
    public function primeReveals(?CompanyProfile $company, iterable $profiles): void
    {
        // تُمسح قبل أن تُملأ: الذاكرة لا تعيش أطول من القراءة التي هيّأتها.
        $this->revealMemo = [];

        if ($company === null) {
            return;
        }

        $ids = [];

        foreach ($profiles as $profile) {
            $ids[] = $profile->id;
        }

        if ($ids === []) {
            return;
        }

        $revealed = CandidateReveal::query()
            ->where('company_profile_id', $company->id)
            ->whereIn('employee_profile_id', $ids)
            ->valid()
            ->pluck('employee_profile_id')
            ->all();

        foreach ($ids as $id) {
            // الغياب من النتيجة جوابٌ أيضاً: `false` تُخزَّن كي لا يُعاد السؤال.
            $this->revealMemo[$company->id.':'.$id] = in_array($id, $revealed, true);
        }
    }

    public function companyOf(?User $viewer): ?CompanyProfile
    {
        return $viewer?->companyProfile;
    }
}
