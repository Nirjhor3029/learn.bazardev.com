<?php

namespace App\Providers;

use App\Services\Learning;
use App\Services\Payments\PayBazarDevGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per request, so a reader's chapter states are loaded only once.
        $this->app->scoped(Learning::class);

        $this->app->bind(PaymentGateway::class, function () {
            $config = config('services.pay_bazardev');

            return new PayBazarDevGateway(
                baseUrl: $config['base_url'],
                apiKey: $config['api_key'] ?? '',
                apiSecret: $config['api_secret'] ?? '',
                liveApiSecret: $config['live_api_secret'] ?? $config['api_secret'] ?? '',
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
