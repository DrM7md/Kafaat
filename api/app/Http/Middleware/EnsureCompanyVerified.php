<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\VerificationStatus;
use App\Models\User;
use App\Support\SettingKeys;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ★★ بوّابة الشركات: لا تصفّح ولا كشف قبل توثيق المشرف.
 *
 * الضمان الحقيقي هنا لا في الواجهة. إخفاء زرٍّ لا يمنع طلباً بـ curl، ومن
 * يتصفّح هو من يرى ملفّات الناس — فشركةٌ وهمية تتصفّح تعني حصادَ بياناتٍ بلا
 * حسيب. لذا يُردّ الطلب في الخادم، وتبقى الواجهة تحسيناً للتجربة لا حاجزاً.
 *
 * ⚠️ الحارس مقصورٌ على **من يملك ملفّ شركة**. المشرف والموظّف يمرّان بلا مساس:
 *    سؤالُنا «هل هذه الشركة موثَّقة؟» لا معنى له لمن ليس شركة، وردُّهم هنا كان
 *    سيحجب المشرفَ عن تصفّحٍ يملكه بحقّ.
 */
final class EnsureCompanyVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        $company = $user?->companyProfile;

        // ليس شركة (مشرفٌ أو موظّف) — لا شأن لهذا الحارس به.
        if ($company === null) {
            return $next($request);
        }

        // مفتاحٌ إداريّ يُطفئ البوّابة. إطفاؤه قرارٌ خطر — والوصف في اللوحة يقوله.
        if (! (bool) settings(SettingKeys::MODERATION_REQUIRE_COMPANY_VERIFICATION, true)) {
            return $next($request);
        }

        if ($company->verification_status === VerificationStatus::Verified) {
            return $next($request);
        }

        // 403 لا 404: لا سرَّ نحميه هنا. الشركة تعرف أنها لم تُوثَّق بعد، بل
        // نريدها أن تعرف — الغموض يجعلها تظنّ المنصّة معطّلة.
        return response()->json([
            'message' => $company->verification_status === VerificationStatus::Rejected
                ? 'رُفض توثيق شركتك. راجع الدعم لمعرفة السبب.'
                : 'حساب شركتك قيد المراجعة. سنُبلغك فور توثيقه.',
            'verification_status' => $company->verification_status->value,
        ], 403);
    }
}
