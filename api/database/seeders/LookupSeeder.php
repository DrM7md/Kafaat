<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        // ألوان الشارات منقولة حرفياً من UI/*.dc.html → STATUS.
        // تعيش في meta لا في CSS: هكذا يضيف المشرف حالةً رابعة بلونها
        // دون سطر برمجي. Brain/05-settings-engine.md §4.
        $statuses = [
            ['leaving', 'راغب في ترك عمله', 'Looking to leave', ['bg' => '#E7F3EA', 'color' => '#1E7A44', 'dot' => '#2FA35C']],
            ['open', 'منفتح على فرص أفضل', 'Open to better offers', ['bg' => '#FBF0D9', 'color' => '#96682A', 'dot' => '#C9922F']],
            ['stable', 'مستقرّ', 'Stable', ['bg' => '#ECEFEE', 'color' => '#566460', 'dot' => '#8A9793']],
        ];

        foreach ($statuses as $i => [$key, $ar, $en, $meta]) {
            $this->upsert(Lookup::EMPLOYMENT_STATUS, $key, $ar, $en, $i, $meta);
        }

        $fields = [
            ['it', 'تقنية المعلومات', 'Information Technology'],
            ['marketing', 'التسويق', 'Marketing'],
            ['finance', 'المالية', 'Finance'],
            ['hr', 'الموارد البشرية', 'Human Resources'],
            ['engineering', 'الهندسة', 'Engineering'],
            ['sales', 'المبيعات', 'Sales'],
            ['legal', 'القانون', 'Legal'],
            ['healthcare', 'الرعاية الصحّية', 'Healthcare'],
            ['education', 'التعليم', 'Education'],
            ['operations', 'العمليات', 'Operations'],
        ];

        foreach ($fields as $i => [$key, $ar, $en]) {
            $this->upsert(Lookup::FIELD, $key, $ar, $en, $i);
        }

        $cities = [
            ['doha', 'الدوحة', 'Doha'],
            ['al-rayyan', 'الريّان', 'Al Rayyan'],
            ['al-wakrah', 'الوكرة', 'Al Wakrah'],
            ['al-khor', 'الخور', 'Al Khor'],
            ['umm-salal', 'أمّ صلال', 'Umm Salal'],
            ['al-shamal', 'الشمال', 'Al Shamal'],
            ['al-daayen', 'الضعاين', 'Al Daayen'],
            ['dukhan', 'دخان', 'Dukhan'],
        ];

        foreach ($cities as $i => [$key, $ar, $en]) {
            $this->upsert(Lookup::CITY, $key, $ar, $en, $i);
        }

        $industries = [
            ['technology', 'التقنية', 'Technology'],
            ['banking', 'المصارف', 'Banking'],
            ['energy', 'الطاقة', 'Energy'],
            ['construction', 'الإنشاءات', 'Construction'],
            ['retail', 'التجزئة', 'Retail'],
            ['consulting', 'الاستشارات', 'Consulting'],
        ];

        foreach ($industries as $i => [$key, $ar, $en]) {
            $this->upsert(Lookup::INDUSTRY, $key, $ar, $en, $i);
        }

        $sizes = [
            ['1-10', '1–10 موظّفين', '1-10'],
            ['11-50', '11–50 موظّفاً', '11-50'],
            ['51-200', '51–200 موظّف', '51-200'],
            ['201-1000', '201–1000 موظّف', '201-1000'],
            ['1000+', 'أكثر من 1000', '1000+'],
        ];

        foreach ($sizes as $i => [$key, $ar, $en]) {
            $this->upsert(Lookup::COMPANY_SIZE, $key, $ar, $en, $i);
        }
    }

    /** @param array<string, string>|null $meta */
    private function upsert(string $group, string $key, string $ar, string $en, int $sort, ?array $meta = null): void
    {
        Lookup::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['label_ar' => $ar, 'label_en' => $en, 'sort_order' => $sort, 'meta' => $meta, 'is_active' => true],
        );
    }
}
