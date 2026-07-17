<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();

            // معرّف عامّ عشوائي كمعرّف الكفاءة (ADR-005): المتسلسل يُتيح تعداد
            // كل وظائف المنصّة وحصر نموّها وحصرَ عملاء المنافس.
            $table->char('ref', 8)->unique();

            $table->foreignId('company_profile_id')->constrained()->cascadeOnDelete();

            $table->string('title', 160);
            $table->text('description');

            $table->foreignId('field_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('lookups')->nullOnDelete();

            $table->unsignedTinyInteger('min_experience')->default(0);
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->char('currency', 3)->default('QAR');

            // draft | open | closed
            //
            // ★ `open` وحدها تُحسَب من الحصّة، وهي وحدها تظهر للموظّفين. إغلاق
            //   وظيفةٍ يحرّر خانتها — فيُثاب من ينظّف لوحته، ولا يمتلئ الموقع
            //   بوظائف ميتة.
            $table->string('status', 16)->default('draft');

            // ★ خانةٌ مشتراة: هذه الوظيفة تجاوزت الحصّة المجّانية ودُفع ثمنها.
            //   تبقى معلَّمةً إلى الأبد — فإغلاقها ثم فتحُ غيرها لا يُلزم الشركةَ
            //   بالدفع مرّتين على ما دفعت فيه أصلاً.
            $table->boolean('is_paid_slot')->default(false);
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // عدّ الخانات المفتوحة لشركة — الاستعلام الأكثر تكراراً.
            $table->index(['company_profile_id', 'status']);

            // لوحة الوظائف للموظّف.
            $table->index(['status', 'published_at']);
            $table->index('field_id');
            $table->index('city_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
