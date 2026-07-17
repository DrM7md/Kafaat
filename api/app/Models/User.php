<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Support\Permissions;
use App\Support\Roles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
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
 *
 * ★ الملفّان **قابلان للعدم**: للمستخدم واحدٌ منهما أو لا شيء (المشرف بلا
 *   ملفّ). بلا هذا التصريح يستنتج Larastan من `HasOne` نوعاً غير قابلٍ للعدم،
 *   فيَعدّ فحصَ `!== null` عبثاً — ويُسكت أداةً كان يجب أن تحرسنا.
 * @property-read EmployeeProfile|null $employeeProfile
 * @property-read CompanyProfile|null $companyProfile
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

    /**
     * كل من يستطيع فعل شيء — أياً كان طريقه إلى الصلاحية.
     *
     * تعيش هنا لا في `Notifier` لأنّها تحمل الفخّ نفسه الذي تشرحه
     * `effectivePermissions()` أعلاه: المشرف العام **لا تُسنَد إليه صلاحيةٌ
     * صراحةً**، فـ `User::permission(...)` من Spatie تُسقطه. مُبلِّغٌ يعتمد
     * عليها وحدها كان سيترك المشرف العام بلا إشعارٍ واحد — وهو ADR-018 يتكرّر.
     *
     * الطرق إلى الصلاحية ثلاثة لا رابع لها: إسنادٌ مباشر، أو دورٌ يحملها،
     * أو `Gate::before`. أوّلان يلتقطهما Spatie، والثالث محصورٌ في المشرف
     * العام — فنضمّه إلى المرشّحين ثم **نترك الـ Gate تقرّر**. فلو تغيّرت
     * قاعدة `Gate::before` غداً، بقي القرارُ لها وحدها.
     *
     * @return Collection<int, self>
     */
    public static function whoCan(string $permission): Collection
    {
        $candidates = self::query()
            ->where('status', UserStatus::Active)
            ->where(function ($query) use ($permission) {
                $query->permission($permission)
                    ->orWhereHas('roles', fn ($q) => $q->where('name', Roles::SUPER_ADMIN));
            })
            ->get();

        return $candidates->filter(
            fn (self $user): bool => Gate::forUser($user)->allows($permission),
        );
    }
}
