<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use GeoIp2\Database\Reader as GeoIP;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\IpUtils;
use WhichBrowser\Parser as UserAgent;
use App\Models\Website;

class WebhookController extends Controller
{
    /**
     * Handle server-side webhook tracking
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function track(Request $request)
    {
        // Validate the incoming request
        $validated = $request->validate([
            'domain' => 'required|string',
            'page' => 'required|string',
            'event' => 'nullable|array',
            'event.name' => 'required_with:event|string',
            'event.value' => 'nullable|numeric',
            'event.unit' => 'nullable|string|max:32',
            'referrer' => 'nullable|string',
            'user_agent' => 'nullable|string',
            'ip' => 'nullable|string|ip',
            'language' => 'nullable|string|max:2',
            'screen_resolution' => 'nullable|string',
        ]);

        // Find the website by domain
        $domain = str_replace(['https://', 'http://', 'www.'], '', mb_strtolower($validated['domain']));
        
        $website = DB::table('websites')
            ->select(['websites.id', 'websites.domain', 'websites.domain_key', 'websites.user_id', 'websites.exclude_bots', 'websites.exclude_ips', 'websites.exclude_params', 'users.can_track'])
            ->join('users', 'users.id', '=', 'websites.user_id')
            ->where('websites.domain', '=', $domain)
            ->first();

        // If the website doesn't exist or user can't track
        if (!$website || !isset($website->can_track) || !$website->can_track) {
            return response()->json(['error' => 'Website not found or tracking disabled'], 404);
        }

        // Verify domain key if restriction is enabled
        if (config('settings.key_restriction') == 1) {
            $domainKey = $request->header('X-Domain-Key');
            
            if (!$domainKey || $domainKey !== $website->domain_key) {
                return response()->json(['error' => 'Invalid domain key'], 403);
            }
        }

        // If the website has excluded IPs and the request provides an IP
        if ($website->exclude_ips && isset($validated['ip'])) {
            $excludedIps = preg_split('/\n|\r/', $website->exclude_ips, -1, PREG_SPLIT_NO_EMPTY);

            if (IpUtils::checkIp($validated['ip'], $excludedIps)) {
                return response()->json(['error' => 'IP address excluded'], 403);
            }
        }

        // Parse user agent if provided
        $ua = null;
        if (isset($validated['user_agent'])) {
            $ua = new UserAgent($validated['user_agent']);
            
            // If the website is excluding bots
            if ($website->exclude_bots && $ua->device->type == 'bot') {
                return response()->json(['error' => 'Bot traffic excluded'], 403);
            }
        }

        // Initialize data array
        $data = $values = [];
        $now = Carbon::now();
        $date = $now->format('Y-m-d');
        $time = $now->format('H');

        // Handle event tracking
        if (isset($validated['event'])) {
            $eventData = $validated['event'];
            
            $event = [
                str_replace(':', ' ', $eventData['name']),
                (isset($eventData['value']) && is_numeric($eventData['value']) && $eventData['value'] > 0 && strlen((string)$eventData['value']) <= 10 ? trim($eventData['value']) : null),
                (isset($eventData['unit']) && mb_strlen($eventData['unit']) <= 32 ? $eventData['unit'] : null)
            ];

            $data['event'] = implode(':', $event);
        } else {
            // Handle pageview
            // Parse the URL to extract query parameters
            $parsedUrl = parse_url($validated['page']);
            $page = [
                'path' => $parsedUrl['path'] ?? '/',
                'query' => $parsedUrl['query'] ?? null
            ];
            
            // Parse query parameters
            parse_str($page['query'] ?? '', $params);
            
            // If the website has excluded query parameters
            if ($website->exclude_params) {
                $excludeQueries = preg_split('/\n|\r/', $website->exclude_params, -1, PREG_SPLIT_NO_EMPTY);

                // If a match all rule is set
                if (in_array('&', $excludeQueries)) {
                    // Remove all parameters
                    $page['query'] = null;
                } else {
                    foreach ($excludeQueries as $param) {
                        // If the excluded parameter exists
                        if (isset($params[$param])) {
                            // Remove the excluded parameter
                            unset($params[$param]);
                        }
                    }

                    // Rebuild the query parameters
                    $page['query'] = http_build_query($params);
                }
            }
            
            // Add the page
            $data['pageviews'] = $date;
            $data['pageviews_hours'] = $time;
            $data['page'] = mb_substr((isset($page['query']) && !empty($page['query']) ? $page['path'].'?'.$page['query'] : $page['path'] ?? '/'), 0, 255);
            
            // Get geolocation information if IP is provided
            $continent = $country = $city = null;
            if (isset($validated['ip'])) {
                try {
                    $geoip = (new GeoIP(storage_path('app/geoip/GeoLite2-City.mmdb')))->city($validated['ip']);
                    
                    $continent = $geoip->continent->code.':'.$geoip->continent->name;
                    $country = $geoip->country->isoCode.':'.$geoip->country->name;
                    $city = $geoip->country->isoCode.': '.$geoip->city->name.(isset($geoip->mostSpecificSubdivision->isoCode) ? ', '.$geoip->mostSpecificSubdivision->isoCode : '');
                } catch (\Exception $e) {
                    // Silently fail if geolocation lookup fails
                }
            }
            
            // Extract browser, OS, device info from user agent
            $browser = $os = $device = null;
            if ($ua) {
                $browser = mb_substr($ua->browser->name ?? null, 0, 64);
                $os = mb_substr($ua->os->name ?? null, 0, 64);
                $device = mb_substr($ua->device->type ?? null, 0, 64);
            }
            
            $language = isset($validated['language']) ? mb_substr($validated['language'], 0, 2) : null;
            $screenResolution = $validated['screen_resolution'] ?? null;
            $referrer = isset($validated['referrer']) ? parse_url($validated['referrer'], PHP_URL_HOST) : null;
            
            // If the request is from a new visitor (based on missing/different referrer)
            if (!$referrer || $referrer !== $domain) {
                // Add the campaign
                if (isset($params['utm_campaign']) && !empty($params['utm_campaign'])) {
                    $data['campaign'] = $params['utm_campaign'];
                }
                
                // Add various visitor data
                if ($continent) $data['continent'] = $continent;
                if ($country) $data['country'] = $country;
                if ($city) $data['city'] = $city;
                if ($browser) $data['browser'] = $browser;
                if ($os) $data['os'] = $os;
                if ($device) $data['device'] = $device;
                if ($language) $data['language'] = $language;
                
                $data['visitors'] = $date;
                $data['visitors_hours'] = $time;
                if ($screenResolution) $data['resolution'] = $screenResolution;
                $data['landing_page'] = $data['page'];
                if ($referrer) $data['referrer'] = mb_substr($referrer, 0, 255);
            }
        }
        
        // Prepare values for database insertion
        foreach ($data as $name => $value) {
            $values[] = "({$website->id}, '{$name}', " . DB::connection()->getPdo()->quote(mb_substr($value, 0, 255)) . ", '{$date}')";
        }
        
        if (!empty($values)) {
            $values = implode(', ', $values);
            
            // Insert into stats table
            DB::statement("INSERT INTO `stats` (`website_id`, `name`, `value`, `date`) VALUES {$values} ON DUPLICATE KEY UPDATE `count` = `count` + 1;");
            
            // Insert into recents table for pageviews
            if (!isset($validated['event'])) {
                $recentParams = [
                    'website_id' => $website->id,
                    'page' => $data['page'],
                    'referrer' => $referrer,
                    'os' => $os,
                    'browser' => $browser,
                    'device' => $device,
                    'country' => $country,
                    'city' => $city,
                    'language' => $language,
                    'timestamp' => $now
                ];
                
                DB::statement("INSERT INTO `recents` (`id`, `website_id`, `page`, `referrer`, `os`, `browser`, `device`, `country`, `city`, `language`, `created_at`) 
                    VALUES (NULL, :website_id, :page, :referrer, :os, :browser, :device, :country, :city, :language, :timestamp)", $recentParams);
            }
        }
        
        return response()->json(['status' => 'success'], 200);
    }
}
