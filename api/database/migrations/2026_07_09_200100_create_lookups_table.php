<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('group', 40);   // employment_status | field | city | industry | company_size
            $table->string('key', 60);
            $table->string('label_ar', 120);
            $table->string('label_en', 120)->nullable();

            // ألوان شارة الحالة الوظيفية تعيش هنا لا في CSS — وإلّا استحال
            // على المشرف إضافة حالة رابعة بلونها دون كود. Brain/05 §4.
            $table->json('meta')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['group', 'key']);
            $table->index(['group', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookups');
    }
};
