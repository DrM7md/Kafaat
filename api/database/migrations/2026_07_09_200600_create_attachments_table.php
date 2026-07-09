<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();   // المعرّف العامّ الوحيد للمرفق
            $table->morphs('attachable');

            $table->string('type', 24);   // cv | certificate | good_conduct | logo

            // ⚠️ قرص خاص دائماً. رابط مباشر إلى التخزين يتجاوز كل الحجب.
            // الوصول حصراً عبر GET /attachments/{uuid} المحروس بـ AttachmentPolicy.
            $table->string('disk', 32)->default('private');
            $table->string('path');

            $table->string('original_name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->boolean('is_private')->default(true);

            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
