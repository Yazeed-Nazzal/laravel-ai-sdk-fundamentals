<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run schedule:run every minute on the server, or schedule:work locally.
Schedule::command('support-issues:escalate')->daily()->withoutOverlapping();
