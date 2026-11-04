<?php

namespace App\Providers;

use App\Services\Hotel\HotelSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HotelSettings::class);
        $this->app->alias(HotelSettings::class, 'hotel.settings');
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Plain JSON objects for single resources (the Angular services expect no `data` wrapper).
        JsonResource::withoutWrapping();

        // Booking creation / payment endpoints: generous for real guests, tight for bots.
        RateLimiter::for('bookings', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
