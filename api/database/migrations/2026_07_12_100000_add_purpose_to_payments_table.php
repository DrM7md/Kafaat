<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // ★ آلة الدفع بُنيت للكشف حصراً: الـ webhook كان يجد الكشفَ بـ
            //   `payment_id` ويرمي إن لم يجده. فدفعةٌ لخانة وظيفة كانت تنهار
            //   في الـ webhook — **بعد** أن يدخل المال.
            //
            //   `purpose` يجعل الـ webhook يعرف ماذا يُتمّ. القيمة الافتراضية
            //   `reveal` فتبقى كل دفعةٍ قائمة على ما هي عليه.
            $table->string('purpose', 16)->default('reveal')->after('company_profile_id');

            $table->index(['purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['purpose', 'status']);
            $table->dropColumn('purpose');
        });
    }
};
