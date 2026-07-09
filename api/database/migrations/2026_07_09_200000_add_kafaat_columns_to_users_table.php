<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ADR-007: بنية الحساب، لا الدور. الصلاحيات كلّها عبر Spatie.
            $table->string('type', 16)->default('employee')->after('id');
            $table->string('status', 16)->default('active')->after('type');

            // 🔒 حقل محجوب — لا يغادر الخادم دون كشف صالح (Brain/06).
            $table->string('phone', 32)->nullable()->after('email');

            $table->string('locale', 5)->default('ar')->after('phone');
            $table->timestamp('last_login_at')->nullable();

            // ADR-012: الحذف إخفاء هوية، لا DELETE. الحذف الناعم يحمي
            // نزاهة الفواتير وسجلّ التدقيق.
            $table->softDeletes();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['type', 'status']);
            $table->dropSoftDeletes();
            $table->dropColumn(['type', 'status', 'phone', 'locale', 'last_login_at']);
        });
    }
};
