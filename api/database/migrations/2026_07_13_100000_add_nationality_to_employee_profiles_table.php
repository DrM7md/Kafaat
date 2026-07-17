<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            // الجنسية **ليست** الدولة: الدولة أين يعمل — منها تُقرأ عملةُ راتبه
            // وإليها تُنسب مدينته. والجنسية من هو — وعليها تُبنى حسابات التأشيرة
            // والتوطين التي تصفّي بها الشركةُ بحثها. حقلان لا واحد.
            //
            // `nullable`: الملفّات القائمة لا جنسية لها، ولا نخترع لها واحدة.
            $table->foreignId('nationality_id')->nullable()->after('country_id')
                ->constrained('lookups')->nullOnDelete();

            // فهرسٌ لأنه صار مِصفاةً في التصفّح — لا حقلَ عرضٍ فقط.
            $table->index('nationality_id');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropForeign(['nationality_id']);
            $table->dropIndex(['nationality_id']);
            $table->dropColumn('nationality_id');
        });
    }
};
