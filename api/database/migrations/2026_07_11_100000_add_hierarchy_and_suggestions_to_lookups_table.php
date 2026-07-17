<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lookups', function (Blueprint $table) {
            // هرميّة عامّة: المدينة تحت دولتها. عمودٌ واحد بدل جدول `countries`
            // منفصل — فتكسب الدولُ مجّاناً كلَّ ما تملكه القوائم: التخصيص من
            // اللوحة، والترتيب، والتعطيل، و`meta`. ويصلح لاحقاً لأي هرمية
            // أخرى (تخصّص تحت مجال) دون مهاجرة جديدة.
            $table->foreignId('parent_id')->nullable()->after('key')
                ->constrained('lookups')->nullOnDelete();

            // دورة حياة الاقتراح القادم من خيار «أخرى».
            //
            // الاقتراح صفٌّ حقيقي في `lookups` لا سجلٌّ في جدولٍ جانبي: وإلّا
            // لاحتاج `employee_profiles.field_id` إلى عمودٍ نصّيٍّ موازٍ، ثم
            // إلى ترحيلٍ عند الاعتماد. هكذا يشير الملفّ إلى القيمة من فورها،
            // والاعتماد يقلب حرفاً واحداً — لا يَنقل بياناً.
            $table->string('status', 16)->default('approved')->after('is_active');
            $table->foreignId('suggested_by_user_id')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();

            $table->index(['group', 'status']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('lookups', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['suggested_by_user_id']);
            $table->dropIndex(['group', 'status']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn(['parent_id', 'status', 'suggested_by_user_id']);
        });
    }
};
