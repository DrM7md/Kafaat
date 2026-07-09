<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| إعداد Pest
|--------------------------------------------------------------------------
|
| كل اختبارات Feature تُشغَّل على قاعدة MySQL نظيفة (RefreshDatabase).
| راجع phpunit.xml — لا نستخدم SQLite عمداً (lockForUpdate بلا أثر فيها).
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');
