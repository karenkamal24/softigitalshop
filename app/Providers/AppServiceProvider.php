<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Payment\MockPaymentGateway;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymobPaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class, function ($app) {
            $manager = new PaymentGatewayManager();
            $manager->registerGateway('mock', new MockPaymentGateway());

            $paymobConfig = config('payment.gateways.paymob', []);
            if (! empty($paymobConfig['api_key'] ?? null)) {
                $manager->registerGateway('paymob', new PaymobPaymentGateway(
                    apiKey: $paymobConfig['api_key'],
                    integrationId: $paymobConfig['integration_id'],
                    iframeId: $paymobConfig['iframe_id'],
                    merchantId: $paymobConfig['merchant_id'],
                    baseUrl: $paymobConfig['base_url'] ?? 'https://accept.paymob.com/api',
                    hmacSecret: $paymobConfig['hmac_secret'] ?? null,
                ));
            }

            return $manager;
        });

        $this->app->bind('payment.gateway', function ($app) {
            $gateway = config('payment.default', 'mock');
            $manager = $app->make(PaymentGatewayManager::class);

            try {
                return $manager->getGateway($gateway);
            } catch (\InvalidArgumentException $e) {
                if ($gateway === 'paymob') {
                    \Illuminate\Support\Facades\Log::warning(
                        'Paymob selected but not configured. Falling back to Mock. Add PAYMOB_API_KEY and PAYMOB_MERCHANT_ID to .env'
                    );

                    return $manager->getGateway('mock');
                }
                throw $e;
            }
        });

        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make('payment.gateway');
        });
    }

    public function boot(): void
    {
        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->user()?->id);
        });
    }
}
