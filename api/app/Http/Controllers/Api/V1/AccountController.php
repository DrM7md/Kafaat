<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\AnonymizeUserAction;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * حقوق صاحب البيانات — قانون حماية البيانات القطري رقم 13.
 * Brain/09-legal-compliance.md §1.
 */
final class AccountController extends Controller
{
    public function __construct(
        private readonly AnonymizeUserAction $anonymize,
        private readonly AuditLogger $audit,
    ) {}

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
