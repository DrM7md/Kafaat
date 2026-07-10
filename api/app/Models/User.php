<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property UserType $type
 * @property UserStatus $status
 */
#[Fillable(['name', 'email', 'phone', 'password', 'type', 'status', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'type' => UserType::class,
            'status' => UserStatus::class,
        ];
    }

    /** @return HasOne<EmployeeProfile, $this> */
    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    /** @return HasOne<CompanyProfile, $this> */
    public function companyProfile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class);
    }

    /** @return HasMany<Consent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * الصلاحيات الفعّالة: الدور + الاستثناءات الفردية + `Gate::before`.
     *
     * ★ تُحسب بسؤال الـ Gate نفسها عن كل صلاحية في الكتالوج، لا بقراءة
     *   `getAllPermissions()`.
     *
     *   السبب: المشرف العام **لا تُسنَد إليه أي صلاحية صراحةً** — يمنحه
     *   `Gate::before` كل شيء. فـ `getAllPermissions()` تُعيد له مصفوفةً
     *   فارغة، وكانت الواجهة تُخفي عنه اللوحة كلّها.
     *
     *   سؤال الـ Gate يحترم كل طبقات القرار دون أن يذكر اسم دورٍ هنا
     *   (ADR-007). المصدر واحد للحقيقة: من يُجيز الفعل في الخلفية هو من
     *   يُظهر زرّه في الواجهة.
     *
     * ⚠️ للعرض فقط. الحماية الحقيقية في Policies وMiddleware.
     *
     * @return list<string>
     */
    public function effectivePermissions(): array
    {
        return array_values(array_filter(
            Permissions::all(),
            fn (string $permission): bool => Gate::forUser($this)->allows($permission),
        ));
    }
}
