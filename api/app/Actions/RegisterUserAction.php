<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ConsentType;
use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Support\Roles;
use App\Support\SettingKeys;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * ★ الجسر الوحيد بين `users.type` والصلاحيات (ADR-007).
 *
 * بعد هذه النقطة لا يُذكر `type` في منطق العمل إطلاقاً — الفحص بالصلاحية.
 */
final class RegisterUserAction
{
    /**
     * @param  array{name: string, email: string, phone?: string|null, password: string, title: string}  $data
     */
    public function employee(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'type' => UserType::Employee,
                'status' => UserStatus::Active,
            ]);

            $user->assignRole(Roles::EMPLOYEE);

            // عبر العلاقة: المفتاح الأجنبي يُضبط تلقائياً ولا يُفتَح للتعبئة الجماعية.
            $user->employeeProfile()->create([
                'title' => $data['title'],
                'visibility' => ProfileVisibility::from(
                    (string) settings(SettingKeys::PRIVACY_PROFILE_VISIBILITY_DEFAULT, 'public')
                ),
            ]);

            $this->recordBaseConsents($user);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null, password: string, legal_name: string, cr_number?: string|null}  $data
     */
    public function company(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'type' => UserType::Company,
                'status' => UserStatus::Active,
            ]);

            $user->assignRole(Roles::COMPANY);

            $user->companyProfile()->create([
                'legal_name' => $data['legal_name'],
                'cr_number' => $data['cr_number'] ?? null,
            ]);

            $this->recordBaseConsents($user);

            return $user;
        });
    }

    /**
     * قانون رقم 13: الموافقة على الشروط والخصوصية تُوثَّق بنسختها ووقتها
     * وعنوان الشبكة. الموافقة على البيانات ذات الطبيعة الخاصّة **ليست** هنا —
     * تُطلب صراحةً ومنفصلةً عند رفع مستندٍ حسّاس. Brain/09 §1.
     */
    private function recordBaseConsents(User $user): void
    {
        foreach ([ConsentType::Terms, ConsentType::Privacy] as $type) {
            $user->consents()->create([
                'type' => $type,
                'version' => (string) settings(
                    $type === ConsentType::Terms
                        ? SettingKeys::PLATFORM_TERMS_VERSION
                        : SettingKeys::PLATFORM_PRIVACY_VERSION,
                    '1.0'
                ),
                'granted_at' => now(),
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        }
    }
}
