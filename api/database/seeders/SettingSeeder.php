<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\SettingKeys as K;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $def) {
            // updateOrCreate على الأعمدة الوصفية فقط — لا نطمس قيمةً عدّلها
            // المشرف من اللوحة بإعادة تشغيل البذرة.
            $existing = Setting::query()->find($def['key']);

            Setting::updateOrCreate(
                ['key' => $def['key']],
                [
                    'value' => $existing !== null ? $existing->value : $def['value'],
                    'type' => $def['type'],
                    'group' => $def['group'],
                    'options' => $def['options'] ?? null,
                    'is_public' => $def['is_public'] ?? false,
                    'description' => $def['description'] ?? null,
                ],
            );
        }
    }

    /**
     * @return list<array{key: string, value: mixed, type: string, group: string, options?: list<mixed>, is_public?: bool, description?: string}>
     */
    private function definitions(): array
    {
        return [
            // ── نموذج الربح ──
            [
                'key' => K::BILLING_MODEL,
                'value' => 'pay_per_reveal',
                'type' => 'enum',
                'group' => 'billing',
                'options' => ['pay_per_reveal', 'subscription', 'hybrid'],
                'is_public' => true,
                'description' => 'نموذج الربح. الاشتراكات مؤجّلة خارج MVP.',
            ],
            [
                'key' => K::BILLING_CURRENCY,
                'value' => 'QAR',
                'type' => 'string',
                'group' => 'billing',
                'is_public' => true,
            ],
            [
                'key' => K::REVEAL_FEE,
                'value' => 75,
                'type' => 'int',
                'group' => 'billing',
                'is_public' => true,
                'description' => 'رسم الكشف الواحد. القيمة الافتراضية من نموذج التصميم (٧٥ ر.ق).',
            ],
            [
                'key' => K::BILLING_GATEWAY,
                'value' => 'manual',
                'type' => 'enum',
                'group' => 'billing',
                'options' => ['manual', 'qpay', 'dibsy', 'stripe'],
                'description' => 'البوّابة المفعّلة. manual = بوّابة تطوير محلّية بلا مال حقيقي.',
            ],

            // ── حجب الحقول ★ ──
            [
                'key' => K::MASKING_FIELDS,
                'value' => [
                    'name', 'initials', 'avatar', 'phone', 'email',
                    'current_employer', 'attachments', 'links',

                    // البند الجزائي ومبلغه: شرطٌ في عقد الموظّف الحالي.
                    // كشفهما قبل الدفع يمنح الشركة ورقة تفاوضٍ مجّاناً،
                    // ويكشف تفاصيل عقده مع صاحب عمله. Brain/03 §2.
                    'notice_period_clause', 'penalty_amount',
                ],
                'type' => 'array',
                'group' => 'masking',
                'description' => 'الحقول الغائبة من استجابة الـ API قبل الكشف. تجسيد مبدأ «لا كود لتغيير قاعدة عمل».',
            ],
            [
                'key' => K::MASKING_CURRENT_EMPLOYER_IN_EXPERIENCE,
                'value' => true,
                'type' => 'bool',
                'group' => 'masking',
                'description' => 'حجب اسم جهة العمل في الخبرة الحالية. الخبرات السابقة مكشوفة.',
            ],
            [
                'key' => K::MASKING_SHOW_REDACTED_CV,
                'value' => false,
                'type' => 'bool',
                'group' => 'masking',
                'description' => 'ADR-009: عرض سيرة ذاتية منقّحة قبل الدفع. مؤجّل — التنقيح الآلي هشّ.',
            ],

            // ── الاسترداد ──
            [
                'key' => K::REFUND_ENABLED,
                'value' => true,
                'type' => 'bool',
                'group' => 'refund',
                'is_public' => true,
            ],
            ['key' => K::REFUND_WINDOW_DAYS, 'value' => 7, 'type' => 'int', 'group' => 'refund', 'is_public' => true],
            ['key' => K::REFUND_PERCENTAGE, 'value' => 100, 'type' => 'int', 'group' => 'refund', 'is_public' => true],
            [
                'key' => K::REFUND_TRIGGER,
                'value' => 'employee_declined',
                'type' => 'enum',
                'group' => 'refund',
                'options' => ['employee_declined', 'no_response', 'manual_only'],
            ],
            [
                'key' => K::REFUND_REBLOCKS_ACCESS,
                'value' => true,
                'type' => 'bool',
                'group' => 'refund',
                'description' => 'ADR-010: يُعاد الحجب بعد الاسترداد. خدمةٌ بلا مقابل خطأ منطقي وقانوني.',
            ],

            // ── الخصوصية ──
            [
                'key' => K::PRIVACY_EMPLOYER_BLIND,
                'value' => true,
                'type' => 'bool',
                'group' => 'privacy',
                'description' => 'الشركة لا ترى ملفّ موظّفها الحالي. يُعاد 404 لا 403 — الوجود نفسه سرّ.',
            ],
            ['key' => K::PRIVACY_NOTIFY_ON_REVEAL, 'value' => true, 'type' => 'bool', 'group' => 'privacy'],
            [
                'key' => K::PRIVACY_PROFILE_VISIBILITY_DEFAULT,
                'value' => 'public',
                'type' => 'enum',
                'group' => 'privacy',
                'options' => ['public', 'hidden'],
            ],

            // ── مفاتيح الميزات ──
            ['key' => K::FEATURE_SUBSCRIPTIONS, 'value' => false, 'type' => 'bool', 'group' => 'features', 'is_public' => true],
            ['key' => K::FEATURE_COMPANY_VERIFICATION, 'value' => false, 'type' => 'bool', 'group' => 'features'],
            ['key' => K::FEATURE_NOTIFICATIONS, 'value' => true, 'type' => 'bool', 'group' => 'features'],
            [
                'key' => K::FEATURE_GOOD_CONDUCT_UPLOAD,
                'value' => false,
                'type' => 'bool',
                'group' => 'features',
                'is_public' => true,
                'description' => 'ADR-013: معطّلة عند الإطلاق. مخاطرة قانونية تفوق قيمتها في MVP.',
            ],
            [
                'key' => K::FEATURE_MATCHING_SCORE,
                'value' => false,
                'type' => 'bool',
                'group' => 'features',
                'is_public' => true,
                'description' => 'نسبة التوافق. رقم ثابت في تصميم 1c — تُعامَل كعنصر عرض لا ميزة.',
            ],

            // ── المنصّة ──
            ['key' => K::PLATFORM_NAME, 'value' => 'كفاءات', 'type' => 'string', 'group' => 'platform', 'is_public' => true],
            ['key' => K::PLATFORM_SUPPORT_EMAIL, 'value' => 'support@kafaat.qa', 'type' => 'string', 'group' => 'platform', 'is_public' => true],
            ['key' => K::PLATFORM_TERMS_VERSION, 'value' => '1.0', 'type' => 'string', 'group' => 'platform', 'is_public' => true],
            ['key' => K::PLATFORM_PRIVACY_VERSION, 'value' => '1.0', 'type' => 'string', 'group' => 'platform', 'is_public' => true],
        ];
    }
}
