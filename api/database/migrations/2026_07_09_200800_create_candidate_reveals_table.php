<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_reveals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->string('source', 16)->default('payment');   // payment|subscription|granted

            // ADR-006: السعر يُجمَّد لحظة الكشف. لو قرأناه من الإعدادات لاحقاً
            // لتغيّرت الفواتير التاريخية كلّما عدّل المشرف السعر.
            $table->unsignedInteger('amount')->default(0);
            $table->char('currency', 3)->default('QAR');

            // ⚠️ `completed` وحدها تفكّ الحجب. pending و refunded لا تفكّانه.
            $table->string('status', 16)->default('pending');   // pending|completed|failed|refunded

            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->timestamp('revealed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('expires_at')->nullable();   // لدعم كشفٍ مؤقّت مستقبلاً

            $table->timestamps();

            $table->index(['company_profile_id', 'status']);
            $table->index('employee_profile_id');
        });

        // ★ القيد الجوهري — «كشف نشط واحد لكل زوج (شركة، موظّف)».
        //
        // MySQL لا يدعم الفهارس الجزئية، فنستعمل عموداً مولَّداً يساوي NULL
        // إلّا للحالتين النشطتين. وMySQL يسمح بتكرار NULL في الفهرس الفريد،
        // فتتحرّر الخانة تلقائياً بعد refunded أو failed.
        //
        // يمنع هذا: خصمين متزامنين للزوج نفسه (pending مزدوج)، وكشفين
        // completed معاً. ويسمح بـ: كشفٍ جديد مدفوع بعد استرداد، واستردادات
        // متعدّدة عبر الزمن (refunded ليست في المفتاح، فلا تصطدم).
        DB::statement(<<<'SQL'
            ALTER TABLE candidate_reveals
            ADD COLUMN active_reveal_key VARCHAR(48)
                GENERATED ALWAYS AS (
                    IF(status IN ('pending', 'completed'),
                       CONCAT(company_profile_id, '-', employee_profile_id),
                       NULL)
                ) STORED,
            ADD UNIQUE INDEX reveals_active_unique (active_reveal_key)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_reveals');
    }
};
