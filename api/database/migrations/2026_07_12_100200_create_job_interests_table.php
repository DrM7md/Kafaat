<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_interests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();

            // ★★ الإعجاب **لا يكشف هوية**.
            //
            //    الشركة ترى بطاقةً محجوبة (مسمّى، خبرة، مهارات) وزرَّ كشف —
            //    تماماً كالتصفّح. ولولا ذلك لانهار نموذج الربح كلّه: تكفي
            //    الشركةَ أن تنشر وظيفةً فيأتيها الناس بأسمائهم بلا ريال.
            //
            //    وما يضيفه الإعجاب أثمنُ من الاسم: **النيّة**. الشركة تعرف أن
            //    هذا المرشّح يريد وظيفتها — فتدفع رسم الكشف عن رغبةٍ لا مخاطرة.

            // انسحابٌ من الإعجاب. لا نحذف الصفّ: الشركة قد تكون دفعت رسم الكشف
            // بناءً عليه، فمحوُه من سجلّها يجعل فاتورةً بلا سبب.
            $table->timestamp('withdrawn_at')->nullable();

            // أوّل مرّةٍ فتحت الشركةُ هذا الإعجاب — لتُميّز الجديد من المقروء.
            $table->timestamp('seen_at')->nullable();

            $table->timestamps();

            // إعجابٌ واحد لكل (وظيفة، مرشّح). الضغط مرّتين لا يُنشئ صفّين.
            $table->unique(['job_posting_id', 'employee_profile_id']);

            $table->index(['job_posting_id', 'withdrawn_at']);
            $table->index('employee_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_interests');
    }
};
