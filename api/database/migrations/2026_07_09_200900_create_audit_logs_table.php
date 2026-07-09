<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // يبقى حتى بعد إخفاء هوية المستخدم — المساءلة تتطلّب ذلك (ADR-012).
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action', 60);   // candidate.revealed, settings.updated, role.assigned…

            $table->string('subject_type')->nullable();

            // نصّيّ لا عدديّ: بعض الكائنات المُدقَّقة مفاتيحها نصّية
            // (Setting.key = 'masking.fields'). التدقيق يجب أن يسع كل شيء.
            $table->string('subject_id', 190)->nullable();

            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->json('meta')->nullable();   // القيم قبل/بعد للتعديلات

            // ADR-011: إلحاقيّ فقط. لا UPDATE، لا DELETE. لا updated_at.
            $table->timestamp('created_at')->index();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'action']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
