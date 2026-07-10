<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\VerificationStatus;
use App\Models\CompanyProfile;
use App\Models\EmployeeProfile;
use App\Models\Lookup;
use App\Models\Skill;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * بيانات العرض — بيئة التطوير فقط.
 * الأشخاص الأربعة منقولون حرفياً من نموذج التصميم UI/*.dc.html → DATA.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = $this->company('شركة الخليج للتقنية', 'gulf@kafaat.qa');
        $rival = $this->company('مصرف الدوحة', 'doha-bank@kafaat.qa');

        $demo = [
            [
                'name' => 'أحمد الكواري', 'email' => 'a.alkuwari@mail.qa', 'phone' => '+974 50 11 92 74',
                'title' => 'مهندس برمجيات أول', 'field' => 'it', 'exp' => 8, 'city' => 'doha',
                'status' => 'leaving', 'skills' => ['React', 'Laravel', 'AWS'],
                'salary' => [22000, 28000], 'featured' => true,
                // ★ موظّف حالي لدى «شركة الخليج للتقنية»: يجب ألّا تراه هي.
                'employer' => $company->id,
            ],
            [
                'name' => 'نورة السليطي', 'email' => 'n.alsulaiti@mail.qa', 'phone' => '+974 77 30 44 19',
                'title' => 'مديرة تسويق رقمي', 'field' => 'marketing', 'exp' => 6, 'city' => 'doha',
                'status' => 'open', 'skills' => ['SEO', 'حملات مدفوعة', 'تحليلات'],
                'salary' => [18000, 24000], 'featured' => false, 'employer' => null,
            ],
            [
                'name' => 'راشد المناعي', 'email' => 'r.almannai@mail.qa', 'phone' => '+974 55 66 22 08',
                'title' => 'محاسب قانوني CPA', 'field' => 'finance', 'exp' => 10, 'city' => 'al-wakrah',
                'status' => 'stable', 'skills' => ['IFRS', 'تدقيق', 'ERP'],
                'salary' => [20000, 26000], 'featured' => false, 'employer' => $rival->id,
            ],
            [
                'name' => 'ليلى الأنصاري', 'email' => 'l.alansari@mail.qa', 'phone' => '+974 33 88 77 42',
                'title' => 'أخصائية موارد بشرية', 'field' => 'hr', 'exp' => 5, 'city' => 'doha',
                'status' => 'open', 'skills' => ['استقطاب', 'تقييم', 'سياسات'],
                'salary' => [15000, 20000], 'featured' => false, 'employer' => null,
            ],

            // ── طلبان ينتظران اعتماد المشرف: لا يظهران في التصفّح ──
            [
                'name' => 'سارة الهاجري', 'email' => 's.alhajri@mail.qa', 'phone' => '+974 66 12 34 56',
                'title' => 'مهندسة بيانات', 'field' => 'it', 'exp' => 4, 'city' => 'al-rayyan',
                'status' => 'open', 'skills' => ['Python', 'SQL', 'Spark'],
                'salary' => [17000, 23000], 'featured' => false, 'employer' => null,
                'approval' => ApprovalStatus::Pending,
            ],
            [
                'name' => 'خالد العطية', 'email' => 'k.alattiyah@mail.qa', 'phone' => '+974 55 98 76 54',
                'title' => 'مدير مشاريع إنشائية', 'field' => 'engineering', 'exp' => 12, 'city' => 'al-khor',
                'status' => 'leaving', 'skills' => ['PMP', 'عقود', 'جدولة'],
                'salary' => [28000, 35000], 'featured' => false, 'employer' => null,
                'approval' => ApprovalStatus::Pending,
            ],
        ];

        foreach ($demo as $row) {
            $this->employee($row);
        }
    }

    private function company(string $legalName, string $email): CompanyProfile
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $legalName,
                'password' => 'password',
                'type' => UserType::Company,
                'status' => UserStatus::Active,
            ],
        );

        $user->syncRoles([Roles::COMPANY]);

        return CompanyProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'legal_name' => $legalName,
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => now(),
            ],
        );
    }

    /** @param array<string, mixed> $row */
    private function employee(array $row): void
    {
        /** @var string $email */
        $email = $row['email'];

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $row['name'],
                'phone' => $row['phone'],
                'password' => 'password',
                'type' => UserType::Employee,
                'status' => UserStatus::Active,
            ],
        );

        $user->syncRoles([Roles::EMPLOYEE]);

        /** @var array{0: int, 1: int} $salary */
        $salary = $row['salary'];

        /** @var ApprovalStatus $approval */
        $approval = $row['approval'] ?? ApprovalStatus::Approved;

        $profile = EmployeeProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'approval_status' => $approval,
                'submitted_at' => now()->subDays(2),
                'approved_at' => $approval === ApprovalStatus::Approved ? now() : null,
                'title' => $row['title'],
                'field_id' => $this->lookupId(Lookup::FIELD, (string) $row['field']),
                'years_experience' => $row['exp'],
                'city_id' => $this->lookupId(Lookup::CITY, (string) $row['city']),
                'employment_status_id' => $this->lookupId(Lookup::EMPLOYMENT_STATUS, (string) $row['status']),
                'expected_salary_min' => $salary[0],
                'expected_salary_max' => $salary[1],
                'summary' => 'خبرة عملية في المجال مع سجلّ إنجازات موثّق.',
                'current_employer_company_id' => $row['employer'],
                'visibility' => ProfileVisibility::Public,
                'is_featured' => $row['featured'],
                'completeness' => 85,
                'published_at' => now(),
            ],
        );

        /** @var list<string> $skillNames */
        $skillNames = $row['skills'];

        $skillIds = collect($skillNames)->map(fn (string $name): int => Skill::firstOrCreate(
            ['name' => $name],
            ['slug' => Str::slug($name) ?: Str::random(8)],
        )->id)->all();

        $profile->skills()->sync($skillIds);
    }

    private function lookupId(string $group, string $key): ?int
    {
        return Lookup::query()->where('group', $group)->where('key', $key)->value('id');
    }
}
