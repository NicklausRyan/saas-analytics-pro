<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Stripe\Stripe;

class StripeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('stripe', function ($app) {
            // Set up the Stripe client with the API key
            Stripe::setApiKey(config('services.stripe.secret'));
            
            // When key restriction is enabled, enforce the domain restriction
            if (config('settings.key_restriction') == 1) {
                // Set up Stripe API client with an API version that supports metadata
                Stripe::setAppInfo(
                    'PHPAnalytics Pro',
                    config('app.version'),
                    url('/')
                );
                
                // Get the current domain making the request
                $domain = request()->getHost();
                if (strpos($domain, 'www.') === 0) {
                    $domain = substr($domain, 4);
                }
                
                // Check if the domain has a registered API key
                $website = \App\Models\Website::where('domain', $domain)->first();
                
                // If the domain has its own API key, use it instead
                if ($website && $website->stripe_api_key) {
                    Stripe::setApiKey($website->stripe_api_key);
                }
            }
            
            return new \Stripe\StripeClient(Stripe::getApiKey());
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
