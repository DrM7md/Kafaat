<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('legal_name', 160);
            $table->string('brand_name', 160)->nullable();
            $table->string('cr_number', 40)->nullable();   // السجلّ التجاري

            $table->string('verification_status', 16)->default('unverified');
            $table->timestamp('verified_at')->nullable();

            $table->string('logo_path')->nullable();
            $table->foreignId('industry_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->foreignId('size_id')->nullable()->constrained('lookups')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
