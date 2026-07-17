<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;

/**
 * الشركات المسجَّلة — ليختار منها الموظّف جهةَ عمله الحالية.
 *
 * ★ هذه هي الوصلة التي كانت مفقودة في عمى صاحب العمل (ADR-004).
 *
 *   آلة الحجب كلّها تتعلّق بـ `current_employer_company_id`: التصفّح يُسقط
 *   الملفّ من الاستعلام، والكشف يُعيد 404 ولو دُفع ثمنه، ولوحة الوظائف تُخفي
 *   وظائف صاحب العمل عن موظّفه. ولم يكن في المنصّة شيءٌ يملأ هذا الحقل —
 *   فكانت الآلة كلّها معطَّلةً في الإنتاج، وكان الموظّف يُعرَض على شركته.
 *
 * ⚠️ لا يكشف هذا المسار شيئاً عن الموظّفين. الشركة كيانٌ تجاريّ يُشهر اسمه —
 *    وأسماء الشركات معروضةٌ أصلاً في لوحة الوظائف. لا حقلَ هنا سوى ما يلزم
 *    الاختيار: معرّفٌ واسم.
 */
final class EmployerController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $employers = CompanyProfile::query()
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'brand_name'])
            ->map(fn (CompanyProfile $company): array => [
                'id' => $company->id,

                // بنفس شكل `Lookup` — فيقبلها نفسُ الـ Combobox الذي يخدم
                // المجالات والمدن، بلا مكوّنٍ جديد.
                'key' => 'company-'.$company->id,

                // ما يُكتب على الباب أوّلاً: الموظّف يعرف «طلبات» ولا يعرف
                // «شركة الخدمات اللوجستية الحديثة ذ.م.م».
                'label' => $company->brand_name ?? $company->legal_name,
            ]);

        return response()->json(['employers' => $employers]);
    }
}
