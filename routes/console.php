<?php

use App\Services\ScheduledBankingService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    app(ScheduledBankingService::class)->chargeMonthlyFees();
})
    ->monthlyOn(1, '00:01')
    ->name('monthly-fees')
    ->withoutOverlapping();


Schedule::call(function () {
    app(ScheduledBankingService::class)->creditMonthlyInterests();
})
    ->monthlyOn(1, '00:05')
    ->name('monthly-interests')
    ->withoutOverlapping();