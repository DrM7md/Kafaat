<?php

declare(strict_types=1);

namespace App\Services\Masking;

use App\Exceptions\ProfileNotVisibleException;
use App\Models\CandidateReveal;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
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
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws ProfileNotVisibleException إن كان المشاهد صاحب العمل الحالي
     */
    public function visibleFields(?User $viewer, EmployeeProfile $profile): FieldSet
    {
        // 1) صاحب الملفّ يرى كل شيء.
        if ($viewer !== null && $viewer->id === $profile->user_id) {
            return FieldSet::all();
        }

        // 2) صلاحية الإشراف تكشف كل شيء — وتُسجَّل. أخطر صلاحية في المنصّة:
        //    من يملكها يرى المنتج كلّه بلا دفع (Brain/04 §3).
        if ($viewer?->can(Permissions::PROFILE_MODERATE) === true) {
            $this->audit->log(AuditLogger::PROFILE_VIEWED_AS_MODERATOR, $profile, [
                'ref' => $profile->ref,
            ]);

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
        /** @var list<string> $masked */
        $masked = (array) settings(SettingKeys::MASKING_FIELDS, []);

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

        return CandidateReveal::query()
            ->where('company_profile_id', $company->id)
            ->where('employee_profile_id', $profile->id)
            ->valid()
            ->exists();
    }

    public function companyOf(?User $viewer): ?CompanyProfile
    {
        return $viewer?->companyProfile;
    }
}
