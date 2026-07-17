<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ConsentType;
use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\VerificationStatus;
use App\Models\User;
use App\Services\Notifications\Events;
use App\Services\Notifications\Notifier;
use App\Support\Permissions;
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
    public function __construct(private readonly Notifier $notifier) {}

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
     * @param  array{name: string, email: string, phone?: string|null, password: string, legal_name: string, cr_number: string}  $data
     */
    public function company(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'type' => UserType::Company,
                'status' => UserStatus::Active,
            ]);

            $user->assignRole(Roles::COMPANY);

            $company = $user->companyProfile()->create([
                'legal_name' => $data['legal_name'],
                'cr_number' => $data['cr_number'],
            ]);

            // ★ `pending` لا `unverified`: الفرق ليس تجميلاً. «غير موثَّقة»
            //   حالةُ إهمالٍ لا يعرف أحدٌ إن كان أحدٌ ينظر فيها؛ و«قيد المراجعة»
            //   وعدٌ بأنّ طلباً قائماً ينتظر قراراً. والفرق يظهر للشركة نفسها
            //   في شاشة الانتظار، وللمشرف في طابوره.
            //
            //   `forceFill` لأن حالة التوثيق يملكها النظام — لا تُدرَج في
            //   fillable، وإلّا وثّقت الشركةُ نفسها بحقلٍ في نموذج التسجيل.
            $company->forceFill(['verification_status' => VerificationStatus::Pending])->save();

            $this->recordBaseConsents($user);

            return $user;
        });

        // خارج المعاملة: إشعارٌ عن تسجيلٍ تراجَع (rollback) أسوأ من لا إشعار.
        $this->notifier->sendToPermission(
            Permissions::COMPANY_APPROVE,
            Events::companyRegistered($data['legal_name']),
        );

        return $user;
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
