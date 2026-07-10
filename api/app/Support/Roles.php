<?php

declare(strict_types=1);

namespace App\Support;

/**
 * أسماء الأدوار البذرية.
 *
 * ⚠️ وجود هذه الثوابت لا يعني أن الكود يفحص الأدوار.
 * المواضع الوحيدة المسموح لها بذكر اسم دور:
 *   1. البذور (RoleSeeder).
 *   2. Gate::before للمشرف العام (AppServiceProvider).
 *   3. إسناد الدور عند التسجيل (RegisterAction).
 * ما عدا ذلك: $user->can('permission') دائماً. راجع Brain/04-rbac.md §1.
 */
final class Roles
{
    public const SUPER_ADMIN = 'super-admin';

    public const EMPLOYEE = 'employee';

    public const COMPANY = 'company';

    public const CONTENT_MODERATOR = 'content-moderator';

    public const SUPPORT = 'support';

    /**
     * الأدوار البذرية وصلاحياتها. المشرف يعدّلها من اللوحة متى شاء.
     *
     * @return array<string, list<string>>
     */
    public static function seed(): array
    {
        return [
            // super-admin لا يُمنح صلاحيات صراحةً — Gate::before يمنحه كل شيء.
            self::SUPER_ADMIN => [],

            self::EMPLOYEE => [
                Permissions::PROFILE_VIEW,
                Permissions::PROFILE_CREATE,
                Permissions::PROFILE_UPDATE,
                Permissions::PROFILE_PUBLISH,
            ],

            self::COMPANY => [
                Permissions::PROFILE_VIEW,
                Permissions::CANDIDATE_BROWSE,
                Permissions::CANDIDATE_REVEAL,
                Permissions::CANDIDATE_CONTACT,
            ],

            self::CONTENT_MODERATOR => [
                Permissions::PROFILE_VIEW,
                Permissions::PROFILE_MODERATE,
                Permissions::PROFILE_APPROVE,
                Permissions::COMPANY_VIEW,
            ],

            self::SUPPORT => [
                Permissions::PROFILE_VIEW,
                Permissions::COMPANY_VIEW,
                Permissions::PAYMENT_VIEW,
                Permissions::AUDIT_VIEW,
            ],
        ];
    }
}
