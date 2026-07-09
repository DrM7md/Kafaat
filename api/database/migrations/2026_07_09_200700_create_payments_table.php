<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained()->restrictOnDelete();

            $table->string('provider', 32);

            // فريد: يمنع إعادة تشغيل الـ webhook (replay) من إحداث أثر مرّتين.
            $table->string('provider_ref')->unique();

            // فريد: يمنع طلبين متزامنين من إنشاء دفعتين.
            $table->string('idempotency_key', 64)->unique();

            $table->unsignedInteger('amount');
            $table->char('currency', 3)->default('QAR');
            $table->unsignedInteger('fee')->default(0);

            $table->string('status', 16)->default('pending');   // pending|paid|failed|refunded
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->json('payload')->nullable();   // استجابة البوّابة الخام

            $table->timestamps();

            $table->index(['company_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
