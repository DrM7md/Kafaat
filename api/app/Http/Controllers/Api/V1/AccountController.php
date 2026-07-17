<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\AnonymizeUserAction;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Resources\MeResource;
use App\Models\Consent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\Events;
use App\Services\Notifications\Notifier;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * حساب المستخدم: بياناتُه عن نفسه، وحقوقه على بياناته.
 *
 * حقوق صاحب البيانات (التصدير والحذف) — قانون حماية البيانات القطري رقم 13،
 * Brain/09-legal-compliance.md §1.
 */
final class AccountController extends Controller
{
    public function __construct(
        private readonly AnonymizeUserAction $anonymize,
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    /**
     * تعديل بيانات الحساب — والشركةُ ملفَّها معه.
     *
     * لا حارس `can:` على المسار: المستخدم يعدّل **نفسه**، والاستعلام لا يخرج
     * من صفّه أصلاً. الحقول التي يملكها الخادم (النوع، الحالة، التوثيق)
     * محجوبةٌ في `UpdateAccountRequest` لا هنا.
     */
    public function update(UpdateAccountRequest $request): MeResource
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();
        $company = $user->companyProfile;

        // ★ تغيير رقم السجلّ يُسقط التوثيق. المشرف وثّق **سجلّاً بعينه**، فلو
        //   بقي الختم بعد تبديله لصارت شركةٌ موثَّقةً بسجلٍّ لم يره أحد — وهي
        //   الثغرة نفسها التي يُغلقها إجبارُ الحقل.
        $revoked = $company !== null
            && $company->verification_status === VerificationStatus::Verified
            && $company->cr_number !== $data['cr_number'];

        DB::transaction(function () use ($user, $company, $data, $revoked): void {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ])->save();

            if ($company === null) {
                return;
            }

            $company->fill([
                'legal_name' => $data['legal_name'],
                'brand_name' => $data['brand_name'] ?? null,
                'cr_number' => $data['cr_number'],
                'industry_id' => $data['industry_id'] ?? null,
                'size_id' => $data['size_id'] ?? null,
            ]);

            if ($revoked) {
                // `forceFill`: حالة التوثيق ليست في `fillable` عمداً — لا يبلغها
                // طلبٌ من مستخدم، بل قرارٌ من الخادم كهذا أو من المشرف.
                $company->forceFill([
                    'verification_status' => VerificationStatus::Pending,
                    'verified_at' => null,
                ]);
            }

            $company->save();
        });

        $this->audit->log('user.account_updated', $user, [
            'company_verification_revoked' => $revoked,
        ]);

        // خارج المعاملة: إشعارٌ عن تعديلٍ تراجَع أسوأ من لا إشعار.
        //
        // الاسم من الجسم لا من النموذج المحفوظ: قد يكون بُدِّل في الطلب نفسه،
        // والمشرف يبحث عن الشركة بما تُسمّى **الآن**.
        if ($revoked) {
            $this->notifier->sendToPermission(
                Permissions::COMPANY_APPROVE,
                Events::companyReverificationNeeded((string) $data['legal_name']),
            );
        }

        return MeResource::make($user->refresh()->loadMissing(['employeeProfile', 'companyProfile']));
    }

    /** تغيير كلمة المرور. القديمةُ شرطٌ — راجع `UpdatePasswordRequest`. */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill(['password' => $request->validated('password')])->save();

        // الجلسة الحالية تحمل بصمة كلمة المرور القديمة، فتُبطلها `AuthenticateSession`
        // عند الطلب التالي — تحديثُها هنا يُبقي صاحبَ التغيير داخلاً.
        if ($request->hasSession()) {
            $request->session()->put('password_hash_web', $user->getAuthPassword());
        }

        $this->audit->log('user.password_changed', $user);

        return response()->json(['message' => 'غُيّرت كلمة المرور.']);
    }

    /** حقّ الوصول: كل بيانات المستخدم بصيغة قابلة للقراءة. */
    public function export(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->load([
            'employeeProfile.skills', 'employeeProfile.experiences',
            'employeeProfile.educations', 'employeeProfile.attachments',
            'companyProfile', 'roles',
        ]);

        $this->audit->log(AuditLogger::DATA_EXPORTED, $user);

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'type' => $user->type->value,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'roles' => $user->roles->pluck('name'),
            'consents' => Consent::query()
                ->where('user_id', $user->id)
                ->get(['type', 'version', 'granted_at', 'revoked_at']),
            'employee_profile' => $user->employeeProfile,
            'company_profile' => $user->companyProfile,
        ]);
    }

    /**
     * حقّ الحذف — يُنفَّذ بإخفاء الهوية لا بـ DELETE (ADR-012).
     * تُحذف البيانات الشخصية، وتبقى سجلّات المعاملات المالية بلا ما يعرّف صاحبها.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirm' => ['accepted'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->anonymize->handle($user);

        Auth::guard('web')->logout();

        // طلبٌ بتوكن (الجوال مستقبلاً) لا جلسة له. لا نفترض وجودها.
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'حُذفت بياناتك الشخصية. تبقى سجلّات المعاملات المالية '
                .'بلا ما يعرّفك، امتثالاً لالتزاماتنا المحاسبية.',
        ]);
    }
}
