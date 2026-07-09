<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            // المفتاح هو المعرّف: reveal.fee, masking.fields, privacy.employer_blind
            $table->string('key', 80)->primary();
            $table->json('value');
            $table->string('type', 16);    // string|int|bool|json|enum|array
            $table->string('group', 40);   // billing|masking|refund|privacy|features|platform
            $table->json('options')->nullable();   // للقيم المحصورة (enum)
            $table->boolean('is_public')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
