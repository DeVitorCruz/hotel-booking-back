<?php

use App\Services\Booking\HoldReleaser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('bookings:release-expired-holds', function (HoldReleaser $holds) {
    $count = $holds->releaseExpired();
    $this->info("Released {$count} expired hold(s).");
})->purpose('Expire unpaid booking holds and free their rooms');

// Requires a cron entry: * * * * * php /path/to/artisan schedule:run
Schedule::command('bookings:release-expired-holds')->everyMinute()->withoutOverlapping();
