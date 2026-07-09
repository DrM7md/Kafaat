<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\Payments\ManualGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Settings\SettingsRepository;
use App\Support\Roles;
use App\Support\SettingKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class);

        // البوّابة تُختار من الإعدادات لا من الكود: استبدالها بـ QPay أو Dibsy
        // لاحقاً تغييرُ قيمةٍ في اللوحة + إضافةُ سائق، لا إعادةُ كتابة.
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            return match ((string) settings(SettingKeys::BILLING_GATEWAY, 'manual')) {
                default => new ManualGateway,
            };
        });
    }

    public function boot(): void
    {
        // ★ الموضع الوحيد في المشروع كلّه الذي يُذكر فيه اسم دور في منطق الوصول.
        // ما عداه: $user->can('permission'). راجع Brain/04-rbac.md §1 و ADR-007.
        Gate::before(fn (User $user) => $user->hasRole(Roles::SUPER_ADMIN) ? true : null);

        // إسقاط خاصّية غير مُعبّأة صامتاً يعني بياناً ضاع دون أن يصرخ أحد.
        // في نظامٍ يتعامل بالمال والخصوصية، الصمت أسوأ من الانهيار.
        Model::preventSilentlyDiscardingAttributes(! App::isProduction());
    }
}
