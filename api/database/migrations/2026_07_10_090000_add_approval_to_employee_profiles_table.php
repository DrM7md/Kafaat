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
        Schema::table('employee_profiles', function (Blueprint $table) {
            // النشر رغبةُ الموظّف (published_at)، والاعتماد قرارُ المنصّة.
            // اجتماعهما شرطُ الظهور في التصفّح.
            $table->string('approval_status', 16)->default('draft')->after('visibility');
            $table->timestamp('submitted_at')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('approved_by');

            $table->index(['approval_status', 'submitted_at']);
        });

        // الملفّات القائمة والمنشورة تُعتبر معتمَدة — لا نُخفيها بأثرٍ رجعي.
        DB::table('employee_profiles')
            ->whereNotNull('published_at')
            ->update(['approval_status' => 'approved', 'approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropIndex(['approval_status', 'submitted_at']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approval_status', 'submitted_at', 'approved_at', 'rejection_reason']);
        });
    }
};
