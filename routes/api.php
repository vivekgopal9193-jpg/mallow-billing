<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\MerchantDashboardController;
use Illuminate\Support\Facades\RateLimiter;



Route::post('/usage', [UsageController::class, 'store']);

Route::get(
    '/merchants/{merchantId}/dashboard',
    [MerchantDashboardController::class, 'show']
);

Route::post('/usage', [UsageController::class, 'store'])
    ->middleware('throttle:60,1');