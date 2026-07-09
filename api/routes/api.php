<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مسارات الـ API — الإصدار v1
|--------------------------------------------------------------------------
|
| البادئة `api/v1` مضبوطة في bootstrap/app.php.
| العقد الكامل موثّق في Brain/07-api-contract.md.
|
*/

Route::get('/health', HealthController::class)->name('health');
