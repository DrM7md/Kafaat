<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class MeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'type' => $this->type->value,
                'status' => $this->status->value,
                'profile' => $this->profileSummary(),
            ],

            // الصلاحيات الفعّالة: الدور + الاستثناءات الفردية، محسوبةً في الخادم.
            // ⚠️ الواجهة تستعملها لإخفاء عناصر — للعرض فقط، لا للأمان.
            'permissions' => $this->effectivePermissions(),

            // الإعدادات العامّة فقط. ما is_public=false لا يغادر الخادم.
            'settings' => app(SettingsRepository::class)->public(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function profileSummary(): ?array
    {
        if ($employee = $this->employeeProfile) {
            return [
                'ref' => $employee->ref,
                'title' => $employee->title,
                'is_published' => $employee->isPublished(),
                'completeness' => $employee->completeness,
            ];
        }

        // ملفّ الشركة كاملاً — لا موجزاً: `MeResource` لا تغادر صاحبَها، وصفحةُ
        // «حسابي» تُعبّئ نموذجها منه فلا تحتاج طلباً ثانياً.
        if ($company = $this->companyProfile) {
            return [
                'id' => $company->id,
                'legal_name' => $company->legal_name,
                'brand_name' => $company->brand_name,
                'cr_number' => $company->cr_number,
                'industry_id' => $company->industry_id,
                'size_id' => $company->size_id,
                'verification_status' => $company->verification_status->value,
            ];
        }

        return null;
    }
}
