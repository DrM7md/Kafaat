<?php

declare(strict_types=1);

use App\Models\Lookup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            // الدولة صريحة، لا مستنتَجة من المدينة: الموظّف قد يختار دولته
            // قبل مدينته، وقد يقترح مدينةً غير موجودة — فتلزم الدولةُ لنعرف
            // أين نضع اقتراحه، ومن أي عملةٍ نقرأ راتبه.
            $table->foreignId('country_id')->nullable()->after('ref')
                ->constrained('lookups')->nullOnDelete();

            $table->index('country_id');
        });

        // الملفّات القائمة كلّها قطرية — المنصّة لم تكن تعرف غير قطر.
        // `currency` كان 'QAR' افتراضاً، فهذا يجعل الضمنيَّ صريحاً.
        $qatar = DB::table('lookups')
            ->where('group', Lookup::COUNTRY)
            ->where('key', 'qa')
            ->value('id');

        if ($qatar !== null) {
            DB::table('employee_profiles')->whereNull('country_id')->update(['country_id' => $qatar]);
        }
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropIndex(['country_id']);
            $table->dropColumn('country_id');
        });
    }
};
