<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('slug', 80)->unique();
            $table->timestamps();
        });

        Schema::create('employee_profile_skill', function (Blueprint $table) {
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['employee_profile_id', 'skill_id']);
        });

        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();

            // 🔒 محجوب إذا is_current — اسم جهة العمل الحالية يعرّض الموظّف.
            // الخبرات السابقة مكشوفة: الماضي لا يعرّضه للخطر. Brain/03 §2.
            $table->string('company_name', 160);
            $table->boolean('is_current')->default(false);

            $table->string('title', 160);
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['employee_profile_id', 'is_current']);
        });

        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->string('institution', 160);
            $table->string('degree', 160);
            $table->string('field_of_study', 160)->nullable();
            $table->year('graduated_year')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educations');
        Schema::dropIfExists('experiences');
        Schema::dropIfExists('employee_profile_skill');
        Schema::dropIfExists('skills');
    }
};
