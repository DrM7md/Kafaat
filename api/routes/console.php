<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// كشفٌ معلّق هُجرت دفعته يحجز خانة الزوج (شركة، موظّف) في الفهرس الفريد
// إلى الأبد. بلا هذه المهمّة، إغلاقُ صفحة الدفع = حرمانٌ دائم من المرشّح.
Schedule::command('kafaat:expire-stale-reveals')
    ->hourly()
    ->withoutOverlapping();
