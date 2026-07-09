<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Permissions;

/**
 * Policy تحرس **الفعل** (هل يفتح هذا الملفّ؟).
 * MaskedProfileResource يحرس **الحقول** (أي حقولٍ منه يرى؟).
 *
 * الاثنان معاً، لا أحدهما. Brain/04-rbac.md §5.
 */
final class EmployeeProfilePolicy
{
    public function view(User $user, EmployeeProfile $profile): bool
    {
        // صاحب الملفّ.
        if ($profile->user_id === $user->id) {
            return true;
        }

        // المشرف يرى الملفّات غير المنشورة أيضاً (للمراجعة والتحكيم).
        if ($user->can(Permissions::PROFILE_MODERATE)) {
            return true;
        }

        // الشركة المتصفّحة — والملفّ منشور.
        // ملاحظة: عمى صاحب العمل **لا يُفحص هنا** بل في
        // ProfileVisibilityService، لأنه يرمي 404 لا 403.
        return $user->can(Permissions::CANDIDATE_BROWSE) && $profile->isPublished();
    }
}
