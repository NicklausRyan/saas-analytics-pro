<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Website;

class VerifyStripeKeyRestriction
{
    /**
     * Handle an incoming request.
     * 
     * This middleware verifies that when key restriction is enabled,
     * Stripe API keys can only be used with the domains they're linked to.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only apply restriction when the feature is enabled
        if (config('settings.key_restriction') == 1) {
            // Check if a Stripe API key is being used
            $stripeApiKey = $request->header('Authorization');
            
            if ($stripeApiKey && strpos($stripeApiKey, 'Bearer sk_') !== false) {
                // Extract the key from the Authorization header
                $stripeApiKey = str_replace('Bearer ', '', $stripeApiKey);
                
                // Get the request domain (from Origin or Referer header)
                $domain = null;
                if ($request->header('Origin')) {
                    $domain = parse_url($request->header('Origin'), PHP_URL_HOST);
                } elseif ($request->header('Referer')) {
                    $domain = parse_url($request->header('Referer'), PHP_URL_HOST);
                }
                
                // Remove www. prefix if present
                if ($domain && strpos($domain, 'www.') === 0) {
                    $domain = substr($domain, 4);
                }
                
                // If we have a domain and an API key, check if they match in our database
                if ($domain) {
                    // Find websites with this API key
                    $website = Website::where('stripe_api_key', $stripeApiKey)->first();
                    
                    // If the API key is registered but the domain doesn't match
                    if ($website && $website->domain !== $domain) {
                        return response()->json([
                            'error' => [
                                'message' => 'This Stripe API key is restricted to ' . $website->domain . ' domain.',
                                'type' => 'api_key_restricted',
                                'code' => 'key_restriction_error',
                            ]
                        ], 403);
                    }
                }
            }
        }
        
        return $next($request);
    }
}
