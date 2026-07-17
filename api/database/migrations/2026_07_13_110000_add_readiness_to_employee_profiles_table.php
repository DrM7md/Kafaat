<?php

declare(strict_types=1);

use App\Support\EmployerName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            // ── مؤشّر الرغبة في الانتقال (0–100) ──
            //
            // ★ الرقم **لا يُرسَل للشركات أبداً**. منه تُشتقّ `employment_status_id`
            //   وحدها، فتُعرض شارةً — كما تُعرض شارةُ الطلب بلا عددها.
            //   عرضُ «87/100» يوهم بدقّةٍ لا وجود لها، ويُشعر الموظّف بأنّه مُقيَّم.
            $table->unsignedTinyInteger('readiness')->nullable()->after('summary');

            // 🔑 نضارة الإشارة.
            //
            // بلا هذا العمود تتعفّن الحالة بصمت: موظّفٌ أعلن رغبته في الترك قبل
            // ثمانية أشهر ثم استقرّ — تبقى شارته كما هي، وتدفع الشركةُ رسم الكشف
            // لتكتشف ذلك بنفسها. التاريخ يجعل الإشارة تُصدَّق لا تُحفَظ مرّةً وتُنسى.
            $table->timestamp('readiness_confirmed_at')->nullable()->after('readiness');

            // 🔑 الوصلة الناقصة في عمى صاحب العمل (ADR-004).
            //
            // كل آلة الحجب تتعلّق بـ `current_employer_company_id`، ولم يكن شيءٌ
            // يملؤه: الموظّف يكتب اسم شركته نصّاً حرّاً. فكان الحقل `NULL` دائماً،
            // وكانت الآلة كلّها كوداً ميّتاً.
            //
            // المفتاح المطبَّع يربط النصّ الحرّ بالشركة المسجَّلة — ويبقى شبكةَ أمان
            // للمطابقة حتى لو تعذّر الربط بالمعرّف. راجع App\Support\EmployerName.
            $table->string('current_employer_name_key', 160)->nullable()
                ->after('current_employer_name');

            $table->index('current_employer_name_key');
        });

        // الطرف المقابل من المطابقة. بلا مفتاحٍ **مفهرَس** على جانب الشركات
        // يصير كلُّ حفظِ ملفٍّ مسحاً لجدول الشركات كلّه بحثاً عن اسمٍ مطابق.
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('legal_name_key', 160)->nullable()->after('legal_name');
            $table->string('brand_name_key', 160)->nullable()->after('brand_name');

            $table->index('legal_name_key');
            $table->index('brand_name_key');
        });

        $this->backfillKeys();
    }

    /**
     * ★★ تعبئة الصفوف القائمة — بلا هذا يبقى الحجب معطَّلاً لكلّ من سبق الهجرة.
     *
     * خطّافا النموذجين (`EmployeeProfile::saving` و`CompanyProfile::saving`)
     * لا يمسّان إلّا ما يُحفَظ بعدهما. فكلُّ شركةٍ سجّلت قبل اليوم يبقى مفتاحُها
     * `NULL`، ولا تُحجَب عن موظّفيها بالاسم، ولا يلتقطها `linkCurrentEmployer`.
     *
     * ⚠️ لا يمكن أن يقع هذا في SQL: التطبيع عربيّ (همزات، تاء مربوطة، صيغٌ
     *    قانونية) ويعيش في PHP. فنمرّ على الصفوف — وهي مئاتٌ لا ملايين.
     *
     * ولا نستعمل النماذج: خطّافاتها تُطلق استعلاماتٍ لا نريدها هنا، والهجرةُ
     * يجب أن تصمد ولو حُذف النموذج غداً.
     */
    private function backfillKeys(): void
    {
        DB::table('company_profiles')
            ->select(['id', 'legal_name', 'brand_name'])
            ->orderBy('id')
            ->chunk(200, function ($companies) {
                foreach ($companies as $company) {
                    DB::table('company_profiles')->where('id', $company->id)->update([
                        'legal_name_key' => EmployerName::key($company->legal_name),
                        'brand_name_key' => EmployerName::key($company->brand_name),
                    ]);
                }
            });

        DB::table('employee_profiles')
            ->select(['id', 'current_employer_name'])
            ->whereNotNull('current_employer_name')
            ->orderBy('id')
            ->chunk(200, function ($profiles) {
                foreach ($profiles as $profile) {
                    DB::table('employee_profiles')->where('id', $profile->id)->update([
                        'current_employer_name_key' => EmployerName::key($profile->current_employer_name),
                    ]);
                }
            });

        // ثمّ نصل الطرفين: من كتب اسم شركته نصّاً وهي مسجَّلة — يُربَط بها الآن.
        DB::table('employee_profiles as e')
            ->join('company_profiles as c', function ($join) {
                $join->on('e.current_employer_name_key', '=', 'c.legal_name_key')
                    ->orOn('e.current_employer_name_key', '=', 'c.brand_name_key');
            })
            ->whereNull('e.current_employer_company_id')
            ->whereNotNull('e.current_employer_name_key')
            ->update(['e.current_employer_company_id' => DB::raw('c.id')]);

        // ★★ والعكس: من رُبط بمعرّفٍ يأخذ مفتاحَ اسم شركته كذلك.
        //
        //    بلا هذا يبقى مفتاحُ كلِّ من اختار شركته من القائمة فارغاً، فلا
        //    يحجبه إلّا المعرّف — وينكشف لتوأم شركته المسجَّل بصيغةٍ أخرى.
        //    راجع EmployeeProfile::linkCurrentEmployer().
        DB::table('employee_profiles as e')
            ->join('company_profiles as c', 'e.current_employer_company_id', '=', 'c.id')
            ->update([
                'e.current_employer_name_key' => DB::raw('COALESCE(c.legal_name_key, c.brand_name_key)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropIndex(['current_employer_name_key']);
            $table->dropColumn(['readiness', 'readiness_confirmed_at', 'current_employer_name_key']);
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropIndex(['legal_name_key']);
            $table->dropIndex(['brand_name_key']);
            $table->dropColumn(['legal_name_key', 'brand_name_key']);
        });
    }
};
