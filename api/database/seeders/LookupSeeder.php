<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedEmploymentStatuses();
        $this->seedFields();

        // المدن تتبع دولتها، فتُبذران معاً.
        $this->seedCountriesAndCities();

        $this->seedNationalities();
        $this->seedCompanyLookups();
    }

    private function seedEmploymentStatuses(): void
    {
        // ألوان الشارات منقولة حرفياً من UI/*.dc.html → STATUS.
        // تعيش في meta لا في CSS: هكذا يضيف المشرف حالةً رابعة بلونها
        // دون سطر برمجي. Brain/05-settings-engine.md §4.
        //
        // ★ و`readiness_min` عتبةُ الحالة على مؤشّر الانتقال (0–100).
        //
        //   الموظّف يحرّك شريطاً، والخادم يشتقّ منه حالته: أعلى حالةٍ عتبتُها
        //   لا تتجاوز نسبته. فلا يُسأل «هل تريد ترك عملك؟» — سؤالٌ لا يُجاب
        //   عنه بصدق، إمّا خوفاً من المدير وإمّا طمعاً في الظهور.
        //
        //   وبالعهد نفسه المقطوع للألوان: حالةٌ خامسة («يبحث بجدّية» عند 85،
        //   بلونٍ أحمر) تُضاف من لوحة القوائم بلا سطرٍ برمجيّ ولا نشر. راجع
        //   App\Services\Readiness\ReadinessMapper.
        $statuses = [
            ['leaving', 'راغب في ترك عمله', 'Looking to leave', ['bg' => '#E7F3EA', 'color' => '#1E7A44', 'dot' => '#2FA35C', 'readiness_min' => 70]],
            ['open', 'منفتح على فرص أفضل', 'Open to better offers', ['bg' => '#FBF0D9', 'color' => '#96682A', 'dot' => '#C9922F', 'readiness_min' => 35]],

            // صفراً لا فراغاً: من لم يبلغ عتبةً هي حالُه. لا يبقى أحدٌ بلا حالة.
            ['stable', 'مستقرّ', 'Stable', ['bg' => '#ECEFEE', 'color' => '#566460', 'dot' => '#8A9793', 'readiness_min' => 0]],
        ];

        foreach ($statuses as $i => [$key, $ar, $en, $meta]) {
            $this->upsert(Lookup::EMPLOYMENT_STATUS, $key, $ar, $en, $i, ['meta' => $meta]);
        }
    }

    private function seedFields(): void
    {
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
    }

    private function seedCountriesAndCities(): void
    {
        // ── الدول ──
        // العملة معطى القائمة لا ثابتٌ في الكود. اليوم قطر وحدها نشطة؛ إضافةُ
        // الإمارات بدرهمها غداً نقرةٌ في اللوحة، لا مهاجرة ولا نشر. ADR-019.
        $countries = [
            ['qa', 'قطر', 'Qatar', ['currency' => 'QAR', 'currency_label' => 'ر.ق', 'dial_code' => '+974'], true],

            // مهيّأة ومعطّلة: البنية جاهزة للتوسّع، والقرار إداريّ.
            ['ae', 'الإمارات', 'United Arab Emirates', ['currency' => 'AED', 'currency_label' => 'د.إ', 'dial_code' => '+971'], false],
            ['sa', 'السعودية', 'Saudi Arabia', ['currency' => 'SAR', 'currency_label' => 'ر.س', 'dial_code' => '+966'], false],
            ['kw', 'الكويت', 'Kuwait', ['currency' => 'KWD', 'currency_label' => 'د.ك', 'dial_code' => '+965'], false],
            ['bh', 'البحرين', 'Bahrain', ['currency' => 'BHD', 'currency_label' => 'د.ب', 'dial_code' => '+973'], false],
            ['om', 'عُمان', 'Oman', ['currency' => 'OMR', 'currency_label' => 'ر.ع', 'dial_code' => '+968'], false],
        ];

        foreach ($countries as $i => [$key, $ar, $en, $meta, $active]) {
            $this->upsert(Lookup::COUNTRY, $key, $ar, $en, $i, ['meta' => $meta, 'is_active' => $active]);
        }

        $qatar = Lookup::query()
            ->where('group', Lookup::COUNTRY)
            ->where('key', 'qa')
            ->firstOrFail();

        // ── المدن ── تتبع دولتها عبر parent_id، فلا تختلط مدن الإمارات بمدن قطر.
        $cities = [
            ['doha', 'الدوحة', 'Doha'],
            ['al-rayyan', 'الريّان', 'Al Rayyan'],
            ['al-wakrah', 'الوكرة', 'Al Wakrah'],
            ['al-khor', 'الخور', 'Al Khor'],
            ['umm-salal', 'أمّ صلال', 'Umm Salal'],
            ['al-shamal', 'الشمال', 'Al Shamal'],
            ['al-daayen', 'الضعاين', 'Al Daayen'],
            ['dukhan', 'دخان', 'Dukhan'],
            ['mesaieed', 'مسيعيد', 'Mesaieed'],
            ['lusail', 'لوسيل', 'Lusail'],
        ];

        foreach ($cities as $i => [$key, $ar, $en]) {
            $this->upsert(Lookup::CITY, $key, $ar, $en, $i, ['parent_id' => $qatar->id]);
        }
    }

    /**
     * كتالوج الجنسيات — بياناتٌ لا منطق، فثابتٌ لا متغيّرٌ محلّي.
     *
     * ليست قائمة الدول: نعمل في ستّ دولٍ خليجية، ويعمل عندنا أهلُ الأرض كلّها.
     * فالقائمة شاملة — والحقلُ في الواجهة **قابلٌ للبحث** (`Combobox`)، وقائمةٌ
     * من مئةٍ ونصف يُكتب فيها حرفان فتنحصر في سطر.
     *
     * ★ الترتيب بحضورها في سوق العمل الخليجي لا بالأبجدية: من يفتح القائمة بلا
     *   بحث يجد نفسه في أوّلها غالباً. ليس تفضيلاً لأحد — بل توفيرُ كتابةٍ على
     *   الأكثرين. و«أخرى» يبقى بابَ من لم يجد نفسه.
     *
     * ⚠️ هذا **الكتالوج**، لا ما تعرضه مِصفاةُ التصفّح. تلك تُشتقّ من جنسيات
     *    المسجَّلين فعلاً — راجع `CandidateController::nationalityFacet`.
     *
     * @var list<array{string, string, string}>
     */
    private const NATIONALITIES = [
        // الخليج
        ['qa', 'قطري', 'Qatari'],
        ['sa', 'سعودي', 'Saudi'],
        ['ae', 'إماراتي', 'Emirati'],
        ['kw', 'كويتي', 'Kuwaiti'],
        ['bh', 'بحريني', 'Bahraini'],
        ['om', 'عُماني', 'Omani'],

        // العرب
        ['eg', 'مصري', 'Egyptian'],
        ['jo', 'أردني', 'Jordanian'],
        ['ps', 'فلسطيني', 'Palestinian'],
        ['lb', 'لبناني', 'Lebanese'],
        ['sy', 'سوري', 'Syrian'],
        ['iq', 'عراقي', 'Iraqi'],
        ['ye', 'يمني', 'Yemeni'],
        ['sd', 'سوداني', 'Sudanese'],
        ['ma', 'مغربي', 'Moroccan'],
        ['tn', 'تونسي', 'Tunisian'],
        ['dz', 'جزائري', 'Algerian'],
        ['ly', 'ليبي', 'Libyan'],
        ['mr', 'موريتاني', 'Mauritanian'],
        ['so', 'صومالي', 'Somali'],
        ['dj', 'جيبوتي', 'Djiboutian'],
        ['km', 'قمري', 'Comorian'],

        // جنوب آسيا وشرقها — أكبر روافد سوق العمل
        ['in', 'هندي', 'Indian'],
        ['pk', 'باكستاني', 'Pakistani'],
        ['bd', 'بنغلاديشي', 'Bangladeshi'],
        ['lk', 'سريلانكي', 'Sri Lankan'],
        ['np', 'نيبالي', 'Nepali'],
        ['ph', 'فلبيني', 'Filipino'],
        ['id', 'إندونيسي', 'Indonesian'],
        ['af', 'أفغاني', 'Afghan'],
        ['bt', 'بوتاني', 'Bhutanese'],
        ['mv', 'مالديفي', 'Maldivian'],
        ['mm', 'ميانماري', 'Burmese'],
        ['th', 'تايلندي', 'Thai'],
        ['vn', 'فيتنامي', 'Vietnamese'],
        ['my', 'ماليزي', 'Malaysian'],
        ['sg', 'سنغافوري', 'Singaporean'],
        ['kh', 'كمبودي', 'Cambodian'],
        ['la', 'لاوسي', 'Laotian'],
        ['bn', 'بروناوي', 'Bruneian'],
        ['tl', 'تيموري', 'Timorese'],
        ['cn', 'صيني', 'Chinese'],
        ['jp', 'ياباني', 'Japanese'],
        ['kr', 'كوري جنوبي', 'South Korean'],
        ['mn', 'منغولي', 'Mongolian'],

        // غرب آسيا وآسيا الوسطى
        ['tr', 'تركي', 'Turkish'],
        ['ir', 'إيراني', 'Iranian'],
        ['az', 'أذربيجاني', 'Azerbaijani'],
        ['am', 'أرمني', 'Armenian'],
        ['ge', 'جورجي', 'Georgian'],
        ['kz', 'كازاخي', 'Kazakh'],
        ['uz', 'أوزبكي', 'Uzbek'],
        ['tm', 'تركماني', 'Turkmen'],
        ['tj', 'طاجيكي', 'Tajik'],
        ['kg', 'قيرغيزي', 'Kyrgyz'],

        // أوروبا وأمريكا الشمالية وأوقيانوسيا
        ['gb', 'بريطاني', 'British'],
        ['us', 'أمريكي', 'American'],
        ['ca', 'كندي', 'Canadian'],
        ['au', 'أسترالي', 'Australian'],
        ['nz', 'نيوزيلندي', 'New Zealander'],
        ['ie', 'إيرلندي', 'Irish'],
        ['fr', 'فرنسي', 'French'],
        ['de', 'ألماني', 'German'],
        ['it', 'إيطالي', 'Italian'],
        ['es', 'إسباني', 'Spanish'],
        ['pt', 'برتغالي', 'Portuguese'],
        ['nl', 'هولندي', 'Dutch'],
        ['be', 'بلجيكي', 'Belgian'],
        ['ch', 'سويسري', 'Swiss'],
        ['at', 'نمساوي', 'Austrian'],
        ['se', 'سويدي', 'Swedish'],
        ['no', 'نرويجي', 'Norwegian'],
        ['dk', 'دنماركي', 'Danish'],
        ['fi', 'فنلندي', 'Finnish'],
        ['is', 'آيسلندي', 'Icelandic'],
        ['lu', 'لوكسمبورغي', 'Luxembourgish'],
        ['mt', 'مالطي', 'Maltese'],
        ['cy', 'قبرصي', 'Cypriot'],
        ['gr', 'يوناني', 'Greek'],
        ['pl', 'بولندي', 'Polish'],
        ['cz', 'تشيكي', 'Czech'],
        ['sk', 'سلوفاكي', 'Slovak'],
        ['hu', 'مجري', 'Hungarian'],
        ['ro', 'روماني', 'Romanian'],
        ['bg', 'بلغاري', 'Bulgarian'],
        ['hr', 'كرواتي', 'Croatian'],
        ['si', 'سلوفيني', 'Slovenian'],
        ['rs', 'صربي', 'Serbian'],
        ['ba', 'بوسني', 'Bosnian'],
        ['mk', 'مقدوني', 'Macedonian'],
        ['al', 'ألباني', 'Albanian'],
        ['me', 'جبل أسود', 'Montenegrin'],
        ['xk', 'كوسوفي', 'Kosovar'],
        ['ru', 'روسي', 'Russian'],
        ['ua', 'أوكراني', 'Ukrainian'],
        ['by', 'بيلاروسي', 'Belarusian'],
        ['md', 'مولدوفي', 'Moldovan'],
        ['lt', 'ليتواني', 'Lithuanian'],
        ['lv', 'لاتفي', 'Latvian'],
        ['ee', 'إستوني', 'Estonian'],

        // أفريقيا
        ['za', 'جنوب أفريقي', 'South African'],
        ['ng', 'نيجيري', 'Nigerian'],
        ['ke', 'كيني', 'Kenyan'],
        ['et', 'إثيوبي', 'Ethiopian'],
        ['er', 'إريتري', 'Eritrean'],
        ['ss', 'جنوب سوداني', 'South Sudanese'],
        ['gh', 'غاني', 'Ghanaian'],
        ['ug', 'أوغندي', 'Ugandan'],
        ['tz', 'تنزاني', 'Tanzanian'],
        ['rw', 'رواندي', 'Rwandan'],
        ['bi', 'بوروندي', 'Burundian'],
        ['cm', 'كاميروني', 'Cameroonian'],
        ['sn', 'سنغالي', 'Senegalese'],
        ['ci', 'إيفواري', 'Ivorian'],
        ['ml', 'مالي', 'Malian'],
        ['ne', 'نيجري', 'Nigerien'],
        ['td', 'تشادي', 'Chadian'],
        ['bf', 'بوركيني', 'Burkinabe'],
        ['bj', 'بنيني', 'Beninese'],
        ['tg', 'توغولي', 'Togolese'],
        ['gn', 'غيني', 'Guinean'],
        ['gw', 'غيني بيساوي', 'Bissau-Guinean'],
        ['sl', 'سيراليوني', 'Sierra Leonean'],
        ['lr', 'ليبيري', 'Liberian'],
        ['gm', 'غامبي', 'Gambian'],
        ['cv', 'كابو فيردي', 'Cape Verdean'],
        ['cd', 'كونغولي (الديمقراطية)', 'Congolese (DRC)'],
        ['cg', 'كونغولي', 'Congolese'],
        ['ga', 'غابوني', 'Gabonese'],
        ['gq', 'غيني استوائي', 'Equatorial Guinean'],
        ['cf', 'أفريقي وسطى', 'Central African'],
        ['ao', 'أنغولي', 'Angolan'],
        ['zm', 'زامبي', 'Zambian'],
        ['zw', 'زيمبابوي', 'Zimbabwean'],
        ['mw', 'ملاوي', 'Malawian'],
        ['mz', 'موزمبيقي', 'Mozambican'],
        ['mg', 'مدغشقري', 'Malagasy'],
        ['mu', 'موريشيوسي', 'Mauritian'],
        ['sc', 'سيشلي', 'Seychellois'],
        ['na', 'ناميبي', 'Namibian'],
        ['bw', 'بوتسواني', 'Botswanan'],
        ['ls', 'ليسوتي', 'Basotho'],
        ['sz', 'إسواتيني', 'Swazi'],

        // أمريكا اللاتينية والكاريبي
        ['mx', 'مكسيكي', 'Mexican'],
        ['br', 'برازيلي', 'Brazilian'],
        ['ar', 'أرجنتيني', 'Argentine'],
        ['cl', 'تشيلي', 'Chilean'],
        ['co', 'كولومبي', 'Colombian'],
        ['pe', 'بيروفي', 'Peruvian'],
        ['ve', 'فنزويلي', 'Venezuelan'],
        ['ec', 'إكوادوري', 'Ecuadorian'],
        ['bo', 'بوليفي', 'Bolivian'],
        ['py', 'باراغواياني', 'Paraguayan'],
        ['uy', 'أوروغواياني', 'Uruguayan'],
        ['cu', 'كوبي', 'Cuban'],
        ['do', 'دومينيكاني', 'Dominican'],
        ['ht', 'هايتي', 'Haitian'],
        ['jm', 'جامايكي', 'Jamaican'],
        ['tt', 'ترينيدادي', 'Trinidadian'],
        ['gt', 'غواتيمالي', 'Guatemalan'],
        ['hn', 'هندوراسي', 'Honduran'],
        ['sv', 'سلفادوري', 'Salvadoran'],
        ['ni', 'نيكاراغوي', 'Nicaraguan'],
        ['cr', 'كوستاريكي', 'Costa Rican'],
        ['pa', 'بنمي', 'Panamanian'],

        // أوقيانوسيا
        ['fj', 'فيجي', 'Fijian'],
        ['pg', 'بابوا غينيا الجديدة', 'Papua New Guinean'],

        // ★ حالةٌ حقيقية في الخليج لا استثناءٌ نظري: من لا جنسية له لا يجوز
        //   أن يُترك بلا خيارٍ يصف نفسه به، فيترك الحقل فارغاً ويسقط من كل
        //   مِصفاة.
        ['stateless', 'بدون جنسية', 'Stateless'],
    ];

    private function seedNationalities(): void
    {
        foreach (self::NATIONALITIES as $i => [$key, $ar, $en]) {
            $this->upsert(Lookup::NATIONALITY, $key, $ar, $en, $i);
        }
    }

    private function seedCompanyLookups(): void
    {
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

    /** @param array{meta?: array<string, string>|null, parent_id?: int|null, is_active?: bool} $extra */
    private function upsert(string $group, string $key, string $ar, string $en, int $sort, array $extra = []): void
    {
        // status عبر forceFill — خارج التعبئة الجماعية (يملكه الخادم).
        Lookup::firstOrNew(['group' => $group, 'key' => $key])
            ->fill([
                'label_ar' => $ar,
                'label_en' => $en,
                'sort_order' => $sort,
                'meta' => $extra['meta'] ?? null,
                'parent_id' => $extra['parent_id'] ?? null,
                'is_active' => $extra['is_active'] ?? true,
            ])
            // البذرة مصدرٌ رسمي — لا تُنتج اقتراحات معلَّقة.
            ->forceFill(['status' => Lookup::STATUS_APPROVED])
            ->save();
    }
}
