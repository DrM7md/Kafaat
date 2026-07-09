<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // قانون رقم 13: الموافقة على معالجة البيانات ذات الطبيعة الخاصّة
            // منفصلة عن الموافقة على الشروط العامّة. لا خانة واحدة تبتلع الاثنين.
            $table->string('type', 24);   // terms | privacy | sensitive_data | marketing

            $table->string('version', 16);   // نسخة الوثيقة الموافَق عليها
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();

            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
