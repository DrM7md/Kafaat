<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attachment;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\Masking\ProfileVisibilityService;
use App\Support\Permissions;

/**
 * المرفقات (السير الذاتية والشهادات) تحوي اسم صاحبها حتماً.
 * رابطٌ مباشر واحد إليها يتجاوز الحجب كلّه ويُلغي نموذج الربح.
 */
final class AttachmentPolicy
{
    public function __construct(private readonly ProfileVisibilityService $visibility) {}

    public function view(User $user, Attachment $attachment): bool
    {
        $owner = $attachment->attachable;

        if (! $owner instanceof EmployeeProfile) {
            return $user->can(Permissions::PROFILE_MODERATE);
        }

        // صاحب الملفّ.
        if ($owner->user_id === $user->id) {
            return true;
        }

        // المشرف.
        if ($user->can(Permissions::PROFILE_MODERATE)) {
            return true;
        }

        // عمى صاحب العمل يسبق كل شيء — حتى الكشف المدفوع.
        if ($this->visibility->isCurrentEmployer($user, $owner)) {
            return false;
        }

        // شركةٌ بكشفٍ صالح لهذا الملفّ تحديداً.
        return $this->visibility->hasValidReveal($this->visibility->companyOf($user), $owner);
    }

    /**
     * الحذف حقُّ صاحب الملفّ وحده.
     *
     * لا يُمنح للمشرف: `profile.moderate` صلاحيةُ **رؤية**، ومحو سيرةِ موظّفٍ
     * ذاتيةً فعلٌ لا رجعة فيه لا تقتضيه المراجعة. وشركةٌ دفعت رسم الكشف تملك
     * حقّ القراءة لا حقّ الإتلاف.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        $owner = $attachment->attachable;

        return $owner instanceof EmployeeProfile && $owner->user_id === $user->id;
    }
}
