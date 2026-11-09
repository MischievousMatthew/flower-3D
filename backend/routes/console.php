<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Orders out for delivery 3 days or more complete themselves (also runs while
// the app is used; this is for a scheduler, if one is ever added)
Artisan::command('orders:auto-complete', function () {
    $done = app(\App\Services\OrderAutoCompleteService::class)->sweep();
    $this->info("Completed {$done} " . ($done === 1 ? 'order' : 'orders') . '.');
})->purpose('Complete orders that have been out for delivery for 3 days or more');
