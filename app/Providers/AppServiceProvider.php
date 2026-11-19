<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('guest-checkin-form', function (Request $request) {
            return Limit::perMinute((int) env('GUEST_BOOK_FORM_PER_MINUTE', 60))->by($request->ip() ?: 'guest-form');
        });

        RateLimiter::for('guest-checkin-submit', function (Request $request) {
            return Limit::perMinutes((int) env('GUEST_BOOK_SUBMIT_DECAY_MINUTES', 10), (int) env('GUEST_BOOK_SUBMIT_MAX_ATTEMPTS', 10))
                ->by($request->ip() ?: 'guest-submit');
        });

        RateLimiter::for('guest-checkout-form', function (Request $request) {
            return Limit::perMinute((int) env('GUEST_BOOK_FORM_PER_MINUTE', 60))->by($request->ip() ?: 'guest-checkout-form');
        });

        RateLimiter::for('guest-checkout-submit', function (Request $request) {
            return Limit::perMinutes((int) env('GUEST_BOOK_CHECKOUT_DECAY_MINUTES', 10), (int) env('GUEST_BOOK_CHECKOUT_MAX_ATTEMPTS', 5))
                ->by($request->ip() ?: 'guest-checkout-submit');
        });
    }
}
