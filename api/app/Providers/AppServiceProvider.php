<?php

declare(strict_types=1);

namespace App\Providers;

use App\Exceptions\PaymentsUnderDevelopmentException;
use App\Models\User;
use App\Services\Masking\ProfileVisibilityService;
use App\Services\Payments\ManualGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Settings\SettingsRepository;
use App\Support\Roles;
use App\Support\SettingKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class);

        // `scoped` لا `bind`: الخدمة تحفظ نتائج فحص الكشف داخل الطلب الواحد.
        // بـ `bind` كانت كل استدعاءة `app(...)` تُنشئ نسخةً جديدة بذاكرةٍ فارغة،
        // فيتكرّر استعلام الكشف لكل مرشّح في الصفحة (N+1).
        $this->app->scoped(ProfileVisibilityService::class);

        // البوّابة تُختار من الإعدادات لا من الكود: استبدالها بـ QPay أو Dibsy
        // لاحقاً تغييرُ قيمةٍ في اللوحة + إضافةُ سائق، لا إعادةُ كتابة.
        //
        // ⚠️ لا `default => new ManualGateway`. كان اختيار «stripe» من اللوحة
        //    يُسقط النظام صامتاً إلى بوّابة التطوير — أي دفعٌ وهميّ يظنّه
        //    المشرف حقيقياً. البوّابة غير المنفَّذة تُعلن عن نفسها بـ 503.
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            $gateway = (string) settings(SettingKeys::BILLING_GATEWAY, 'manual');

            return match ($gateway) {
                'manual' => new ManualGateway,
                default => throw new PaymentsUnderDevelopmentException(
                    "البوّابة «{$gateway}» غير منفَّذة بعد."
                ),
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

        // سياسة كلمة المرور الدنيا لكل تسجيلٍ وتغيير: عشرةُ أحرفٍ بأحرفٍ
        // مختلطة وأرقام. `uncompromised` (فحص haveibeenpwned) في الإنتاج
        // وحده — كي لا يُدخل الاختبارُ ولا التطوير المحلّي نداءَ شبكةٍ هشّاً
        // ولا يفشل بكلمة مرورٍ اختباريةٍ معروفة التسريب.
        Password::defaults(function (): Password {
            $rule = Password::min(10)->mixedCase()->numbers();

            return App::isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
