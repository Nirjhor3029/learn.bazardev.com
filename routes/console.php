<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// See App\Console\Commands\ReconcilePayments - covers pay.bazardev statuses
// (expired, failed) that its webhook never actually fires for.
Schedule::command('app:reconcile-payments')->everyFifteenMinutes();
