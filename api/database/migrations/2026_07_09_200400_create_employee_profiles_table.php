<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // ADR-005: المعرّف العامّ عشوائي غير متسلسل — يمنع تعداد الملفّات
            // وحصر حجم القاعدة. كل المسارات العامّة تستقبله لا الـ id.
            $table->char('ref', 8)->unique();

            // ── حقول مكشوفة (جوهر العرض وأساس الفلترة) ──
            $table->string('title', 160);
            $table->foreignId('field_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->unsignedTinyInteger('years_experience')->default(0);
            $table->foreignId('city_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->foreignId('employment_status_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->unsignedInteger('expected_salary_min')->nullable();
            $table->unsignedInteger('expected_salary_max')->nullable();
            $table->char('currency', 3)->default('QAR');
            $table->text('summary')->nullable();

            // ── حقول محجوبة حتى الكشف ──
            $table->text('notice_period_clause')->nullable();   // البند الجزائي
            $table->unsignedInteger('penalty_amount')->nullable();

            // 🔑 مفتاح قاعدة عمى صاحب العمل (Brain/06 §4، ADR-004).
            // الشركة لا ترى ملفّ موظّفها الحالي — ويُعاد 404 لا 403،
            // لأن الوجود نفسه سرّ.
            $table->foreignId('current_employer_company_id')->nullable()
                ->constrained('company_profiles')->nullOnDelete();
            $table->string('current_employer_name', 160)->nullable();

            $table->string('visibility', 16)->default('public');   // public | hidden
            $table->boolean('is_featured')->default(false);
            $table->unsignedTinyInteger('completeness')->default(0);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // فهارس الفلترة — Brain/03 §2
            $table->index(['field_id', 'years_experience']);
            $table->index('city_id');
            $table->index('employment_status_id');
            $table->index(['visibility', 'published_at']);
            $table->index('current_employer_company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
