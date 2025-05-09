<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValidateWebsitePasswordRequest;
use App\Traits\DateRangeTrait;
use App\Models\Website;
use App\Models\Recent;
use App\Models\Stat;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use League\Csv as CSV;

class StatController extends Controller
{
    use DateRangeTrait;

    /**
     * Show the Overview stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        }

        $range = $this->range();

        $visitorsMap = $this->getTraffic($website, $range, 'visitors');
        $pageviewsMap = $this->getTraffic($website, $range, 'pageviews');
        $totalVisitors = $totalPageviews = 0;
        foreach ($visitorsMap as $key => $value) {
            $totalVisitors = $totalVisitors + $value;
        }
        foreach ($pageviewsMap as $key => $value) {
            $totalPageviews = $totalPageviews + $value;
        }

        // Calculate single-page sessions (for bounce rate)
        $singlePageSessions = Stat::where([['website_id', '=', $website->id], ['name', '=', 'landing_page']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->count();

        // Calculate bounce rate (single-page sessions ÷ total sessions)
        // Avoid division by zero
        $bounceRate = $totalVisitors > 0 ? ($singlePageSessions / $totalVisitors) * 100 : 0;

        // Calculate average session time
        // Assumption: Multiple entries in Recent table for the same visitor within a session timeframe represent session duration
        // Get the sessions data grouped by visitor
        $sessionData = Recent::where('website_id', '=', $website->id)
            ->whereBetween('created_at', [$range['from'] . ' 00:00:00', $range['to'] . ' 23:59:59'])
            ->orderBy('created_at')
            ->get(['id', 'created_at'])
            ->groupBy(function ($item) {
                // Group by hour to simulate sessions (since we don't have actual session IDs)
                return Carbon::parse($item->created_at)->format('Y-m-d H');
            });

        $totalSessionTime = 0;
        $sessionCount = 0;

        foreach ($sessionData as $sessions) {
            if ($sessions->count() > 1) {
                // Calculate time between first and last hit in seconds
                $firstHit = Carbon::parse($sessions->first()->created_at);
                $lastHit = Carbon::parse($sessions->last()->created_at);
                $sessionTime = $lastHit->diffInSeconds($firstHit);

                // Add to total
                $totalSessionTime += $sessionTime;
                $sessionCount++;
            }
        }

        // Calculate average session time in seconds
        $avgSessionTime = $sessionCount > 0 ? $totalSessionTime / $sessionCount : 0;

        // Create a bounce rate map for the chart (calculate for each day/period)
        $bounceRateMap = [];
        // Create average session time map for the chart
        $avgSessionTimeMap = [];
        // Calculate bounce rate and average session time for each time period in the map
        if ($range['unit'] == 'hour') {
            // For hourly data (single day)
            foreach ($visitorsMap as $hour => $visitors) {
                // For hourly data, we use the landing_page_hours stats
                $hourSinglePageSessions = Stat::where([
                    ['website_id', '=', $website->id],
                    ['name', '=', 'landing_page_hours'],
                    ['value', '=', $hour]
                ])
                    ->whereBetween('date', [$range['from'], $range['to']])
                    ->sum('count');

                // Avoid division by zero
                $bounceRateMap[$hour] = $visitors > 0 ? ($hourSinglePageSessions / $visitors) * 100 : 0;

                // Calculate average session time for this hour
                $hourDateTime = $range['from'] . ' ' . $hour . ':00:00';
                $nextHour = $range['from'] . ' ' . $hour . ':59:59';

                // Get session data for this hour
                $hourSessionData = Recent::where('website_id', '=', $website->id)
                    ->whereBetween('created_at', [$hourDateTime, $nextHour])
                    ->orderBy('created_at')
                    ->get(['id', 'created_at', 'page'])
                    ->groupBy(function ($item) {
                        // Group by page to simulate sessions
                        return $item->page;
                    });

                $hourTotalSessionTime = 0;
                $hourSessionCount = 0;

                foreach ($hourSessionData as $sessions) {
                    if ($sessions->count() > 1) {
                        // Calculate time between first and last hit in seconds
                        $firstHit = Carbon::parse($sessions->first()->created_at);
                        $lastHit = Carbon::parse($sessions->last()->created_at);
                        $sessionTime = $lastHit->diffInSeconds($firstHit);

                        // Add to total
                        $hourTotalSessionTime += $sessionTime;
                        $hourSessionCount++;
                    }
                }

                // Calculate average session time in seconds
                $avgSessionTimeMap[$hour] = $hourSessionCount > 0 ? $hourTotalSessionTime / $hourSessionCount : 0;
            }
        } else {
            // For daily, monthly, yearly data
            foreach ($visitorsMap as $date => $visitors) {
                // Format the period date correctly based on the unit
                $periodStart = null;
                $periodEnd = null;

                if ($range['unit'] == 'day') {
                    // For daily data
                    $periodStart = $date . ' 00:00:00';
                    $periodEnd = $date . ' 23:59:59';
                } elseif ($range['unit'] == 'month') {
                    // For monthly data - get first and last day of month
                    $dateObj = Carbon::createFromFormat('Y-m', $date);
                    $periodStart = $dateObj->copy()->startOfMonth()->format('Y-m-d 00:00:00');
                    $periodEnd = $dateObj->copy()->endOfMonth()->format('Y-m-d 23:59:59');
                } else {
                    // For yearly data - get first and last day of year
                    $dateObj = Carbon::createFromFormat('Y', $date);
                    $periodStart = $dateObj->copy()->startOfYear()->format('Y-m-d 00:00:00');
                    $periodEnd = $dateObj->copy()->endOfYear()->format('Y-m-d 23:59:59');
                }

                // Get single page sessions for this period
                $periodSinglePageSessions = Stat::where([
                    ['website_id', '=', $website->id],
                    ['name', '=', 'landing_page']
                ])
                    ->whereBetween('date', [$periodStart, $periodEnd])
                    ->sum('count');

                // Avoid division by zero
                $bounceRateMap[$date] = $visitors > 0 ? ($periodSinglePageSessions / $visitors) * 100 : 0;

                // Calculate average session time for this period
                $periodSessionData = Recent::where('website_id', '=', $website->id)
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->orderBy('created_at')
                    ->get(['id', 'created_at', 'page'])
                    ->groupBy(function ($item) {
                        // Group by page to simulate sessions (since we don't have actual session IDs)
                        return $item->page;
                    });

                $periodTotalSessionTime = 0;
                $periodSessionCount = 0;

                foreach ($periodSessionData as $sessions) {
                    if ($sessions->count() > 1) {
                        // Calculate time between first and last hit in seconds
                        $firstHit = Carbon::parse($sessions->first()->created_at);
                        $lastHit = Carbon::parse($sessions->last()->created_at);
                        $sessionTime = $lastHit->diffInSeconds($firstHit);

                        // Add to total
                        $periodTotalSessionTime += $sessionTime;
                        $periodSessionCount++;
                    }
                }

                // Calculate average session time in seconds
                $avgSessionTimeMap[$date] = $periodSessionCount > 0 ? $periodTotalSessionTime / $periodSessionCount : 0;
            }
        }

        $totalVisitorsOld = Stat::where([['website_id', '=', $website->id], ['name', '=', 'visitors']])
            ->whereBetween('date', [$range['from_old'], $range['to_old']])
            ->sum('count');
        // Calculate bounce rate for previous period
        $singlePageSessionsOld = Stat::where([['website_id', '=', $website->id], ['name', '=', 'landing_page']])
            ->whereBetween('date', [$range['from_old'], $range['to_old']])
            ->count();

        $bounceRateOld = $totalVisitorsOld > 0 ? ($singlePageSessionsOld / $totalVisitorsOld) * 100 : 0;

        // Calculate average session time for previous period
        $sessionDataOld = Recent::where('website_id', '=', $website->id)
            ->whereBetween('created_at', [$range['from_old'] . ' 00:00:00', $range['to_old'] . ' 23:59:59'])
            ->orderBy('created_at')
            ->get(['id', 'created_at', 'page'])
            ->groupBy(function ($item) {
                // Group by page to simulate sessions
                return $item->page;
            });

        $totalSessionTimeOld = 0;
        $sessionCountOld = 0;

        foreach ($sessionDataOld as $sessions) {
            if ($sessions->count() > 1) {
                // Calculate time between first and last hit in seconds
                $firstHit = Carbon::parse($sessions->first()->created_at);
                $lastHit = Carbon::parse($sessions->last()->created_at);
                $sessionTime = $lastHit->diffInSeconds($firstHit);

                // Add to total
                $totalSessionTimeOld += $sessionTime;
                $sessionCountOld++;
            }
        }

        // Calculate average session time in seconds for previous period
        $avgSessionTimeOld = $sessionCountOld > 0 ? $totalSessionTimeOld / $sessionCountOld : 0;

        $totalPageviewsOld = Stat::where([['website_id', '=', $website->id], ['name', '=', 'pageviews']])
            ->whereBetween('date', [$range['from_old'], $range['to_old']])
            ->sum('count');

        $pages = $this->getPages($website, $range, null, null, 'count', 'desc')
            ->limit(5)
            ->get();

        $totalReferrers = Stat::where([['website_id', '=', $website->id], ['name', '=', 'referrer']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->sum('count');

        $referrers = $this->getReferrers($website, $range, null, null, 'count', 'desc')
            ->limit(5)
            ->get();

        $countries = $this->getCountries($website, $range, null, null, 'count', 'desc')
            ->limit(5)
            ->get();

        $browsers = $this->getBrowsers($website, $range, null, null, 'count', 'desc')
            ->limit(5)
            ->get();

        $operatingSystems = $this->getOperatingSystems($website, $range, null, null, 'count', 'desc')
            ->limit(5)
            ->get();

        $events = $this->getEvents($website, $range, null, null, 'count', 'desc')
            ->limit(5)
            ->get();
        // Format average session time for display (MM:SS format)
        $formattedAvgSessionTime = gmdate("i:s", round($avgSessionTime));
        $formattedAvgSessionTimeOld = gmdate("i:s", round($avgSessionTimeOld));

        return view('stats.container', [
            'view' => 'overview',
            'website' => $website,
            'range' => $range,
            'referrers' => $referrers,
            'pages' => $pages,
            'visitorsMap' => $visitorsMap,
            'pageviewsMap' => $pageviewsMap,
            'bounceRateMap' => $bounceRateMap,
            'avgSessionTimeMap' => $avgSessionTimeMap,
            'countries' => $countries,
            'browsers' => $browsers,
            'operatingSystems' => $operatingSystems,
            'events' => $events,
            'totalVisitors' => $totalVisitors,
            'totalPageviews' => $totalPageviews,
            'totalVisitorsOld' => $totalVisitorsOld,
            'totalPageviewsOld' => $totalPageviewsOld,
            'totalReferrers' => $totalReferrers,
            'bounceRate' => $bounceRate,
            'bounceRateOld' => $bounceRateOld,
            'avgSessionTime' => $avgSessionTime,
            'avgSessionTimeOld' => $avgSessionTimeOld,
            'avgSessionTimeFormatted' => $formattedAvgSessionTime,
            'avgSessionTimeOldFormatted' => $formattedAvgSessionTimeOld
        ]);
    }

    /**
     * Show the Realtime stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View
     * @throws \Throwable
     */
    public function realtime(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();

        if ($request->wantsJson()) {
            // Date ranges
            $to = Carbon::now();
            $from = $to->copy()->subMinutes(1);
            $to_old = (clone $from)->subSecond(1);
            $from_old = (clone $to_old)->subMinutes(1);

            // Get the available dates
            $visitorsMap = $pageviewsMap = $this->calcAllDates($from, $to, 'second', 'Y-m-d H:i:s', ['count' => 0]);

            $visitors = Recent::selectRaw('COUNT(`website_id`) as `count`, `created_at`')
                ->where('website_id', '=', $website->id)
                ->where(function ($query) use ($website) {
                    $query->where('referrer', '<>', $website->domain)
                        ->orWhereNull('referrer');
                })
                ->whereBetween('created_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
                ->groupBy('created_at')
                ->get();

            $pageviews = Recent::selectRaw('COUNT(`website_id`) as `count`, `created_at`')
                ->where([['website_id', '=', $website->id]])
                ->whereBetween('created_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
                ->groupBy('created_at')
                ->get();

            $recent = Recent::where([['website_id', '=', $website->id]])
                ->whereBetween('created_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
                ->orderBy('id', 'desc')
                ->limit(25)
                ->get();

            $visitorsOld = Recent::where('website_id', '=', $website->id)
                ->where(function ($query) use ($website) {
                    $query->where('referrer', '<>', $website->domain)
                        ->orWhereNull('referrer');
                })
                ->whereBetween('created_at', [$from_old->format('Y-m-d H:i:s'), $to_old->format('Y-m-d H:i:s')])
                ->count();

            $pageviewsOld = Recent::where([['website_id', '=', $website->id]])
                ->whereBetween('created_at', [$from_old->format('Y-m-d H:i:s'), $to_old->format('Y-m-d H:i:s')])
                ->count();

            $totalVisitors = $totalPageviews = 0;

            // Map the values to each date
            foreach ($visitors as $visitor) {
                // Map the value to each date
                $visitorsMap[$visitor->created_at->format('Y-m-d H:i:s')] = $visitor->count;
                $totalVisitors = $totalVisitors + $visitor->count;
            }

            foreach ($pageviews as $pageview) {
                // Map the value to each date
                $pageviewsMap[$pageview->created_at->format('Y-m-d H:i:s')] = $pageview->count;
                $totalPageviews = $totalPageviews + $pageview->count;
            }

            $visitorsCount = $pageviewsCount = [];
            foreach ($visitorsMap as $key => $value) {
                // Remap the key
                $visitorsCount[Carbon::createFromDate($key)->diffForHumans(['options' => Carbon::JUST_NOW])] = $value;
            }

            foreach ($pageviewsMap as $key => $value) {
                // Remap the key
                $pageviewsCount[Carbon::createFromDate($key)->diffForHumans(['options' => Carbon::JUST_NOW])] = $value;
            }
            // For realtime data, we use recents table to calculate bounce rate
            // Count unique pages per visit in the current time frame
            // A visitor with only one page view is considered a bounce
            $recentVisits = Recent::where('website_id', '=', $website->id)
                ->whereBetween('created_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
                ->get(['id', 'page', 'referrer', 'created_at'])
                ->groupBy(function ($visit) {
                    // Group by referring domain and page as a simple visitor identifier
                    // This is a simplified approach without a visitor ID
                    return $visit->referrer . '|' . $visit->page;
                });
            // Count single page sessions and calculate session times
            $singlePageSessions = 0;
            $totalSessionTime = 0;
            $sessionCount = 0;

            foreach ($recentVisits as $visitorGroup) {
                if (count($visitorGroup) === 1) {
                    $singlePageSessions++;
                } else {
                    // Calculate session time for multi-page visits
                    $firstHit = Carbon::parse($visitorGroup->first()->created_at);
                    $lastHit = Carbon::parse($visitorGroup->last()->created_at);
                    $sessionTime = $lastHit->diffInSeconds($firstHit);

                    $totalSessionTime += $sessionTime;
                    $sessionCount++;
                }
            }

            $bounceRateValue = $totalVisitors > 0 ? ($singlePageSessions / $totalVisitors) * 100 : 0;
            $avgSessionTimeValue = $sessionCount > 0 ? $totalSessionTime / $sessionCount : 0;

            // Calculate bounce rate and average session time for previous period
            $recentVisitsOld = Recent::where('website_id', '=', $website->id)
                ->whereBetween('created_at', [$from_old->format('Y-m-d H:i:s'), $to_old->format('Y-m-d H:i:s')])
                ->get(['id', 'page', 'referrer', 'created_at'])
                ->groupBy(function ($visit) {
                    return $visit->referrer . '|' . $visit->page;
                });

            $singlePageSessionsOld = 0;
            $totalSessionTimeOld = 0;
            $sessionCountOld = 0;

            foreach ($recentVisitsOld as $visitorGroup) {
                if (count($visitorGroup) === 1) {
                    $singlePageSessionsOld++;
                } else {
                    // Calculate session time for multi-page visits
                    $firstHit = Carbon::parse($visitorGroup->first()->created_at);
                    $lastHit = Carbon::parse($visitorGroup->last()->created_at);
                    $sessionTime = $lastHit->diffInSeconds($firstHit);

                    $totalSessionTimeOld += $sessionTime;
                    $sessionCountOld++;
                }
            }

            $bounceRateOld = $visitorsOld > 0 ? ($singlePageSessionsOld / $visitorsOld) * 100 : 0;
            $avgSessionTimeOld = $sessionCountOld > 0 ? $totalSessionTimeOld / $sessionCountOld : 0;
            // Format average session time for display (MM:SS format)
            $formattedAvgSessionTime = gmdate("i:s", round($avgSessionTimeValue));
            $formattedAvgSessionTimeOld = gmdate("i:s", round($avgSessionTimeOld));

            return response()->json([
                'visitors' => $visitorsCount,
                'pageviews' => $pageviewsCount,
                'bounce_rate' => $bounceRateValue,
                'avg_session_time' => $avgSessionTimeValue, // In seconds
                'avg_session_time_formatted' => $formattedAvgSessionTime, // Formatted for display
                'visitors_growth' => view('stats.growth', ['growthCurrent' => $totalVisitors, 'growthPrevious' => $visitorsOld])->render(),
                'pageviews_growth' => view('stats.growth', ['growthCurrent' => $totalPageviews, 'growthPrevious' => $pageviewsOld])->render(),
                'bounce_rate_growth' => view('stats.growth', ['growthCurrent' => $bounceRateValue, 'growthPrevious' => $bounceRateOld, 'reverseColors' => true])->render(),
                'avg_session_time_growth' => view('stats.growth', ['growthCurrent' => $avgSessionTimeValue, 'growthPrevious' => $avgSessionTimeOld])->render(),
                'recent' => view('stats.recent', ['website' => $website, 'range' => $range, 'recent' => $recent])->render(),
                'status' => 200
            ], 200);
        }

        return view('stats.container', ['view' => 'realtime', 'website' => $website, 'range' => $range]);
    }

    /**
     * Show the Pages stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function pages(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'page']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $pages = $this->getPages($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getPages($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getPages($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'pages', 'website' => $website, 'range' => $range, 'export' => 'stats.export.pages', 'pages' => $pages, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Landing Pages stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function landingPages(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'landing_page']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $landingPages = $this->getLandingPages($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getLandingPages($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getLandingPages($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'landing-pages', 'website' => $website, 'range' => $range, 'export' => 'stats.export.landing_pages', 'landingPages' => $landingPages, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Exit Pages stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function exitPages(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        }

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'exit_page']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $exitPages = $this->getExitPages($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);        $first = $this->getExitPages($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getExitPages($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'exit-pages', 'website' => $website, 'range' => $range, 'export' => 'stats.export.exit_pages', 'exitPages' => $exitPages, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Export the Exit Pages stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
     * @throws CSV\CannotInsertRecord
     */    public function exportExitPages(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        }

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Exit pages'), $range, __('URL'), __('Visitors'), $this->getExitPages($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Get the Exit Pages stats.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $searchBy
     * @param null $sortBy
     * @param null $sort
     * @return mixed
     */
    private function getExitPages($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'exit_page']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Show the Referrers stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function referrers(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'referrer'], ['value', '<>', $website->domain], ['value', '<>', '']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $referrers = $this->getReferrers($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getReferrers($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getReferrers($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'referrers', 'website' => $website, 'range' => $range, 'export' => 'stats.export.referrers', 'referrers' => $referrers, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Search Engines stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function searchEngines(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');
        $websites = $this->getSearchEnginesList();

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'referrer']])
            ->whereIn('value', $websites)
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $searchEngines = $this->getSearchEngines($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getSearchEngines($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getSearchEngines($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'search-engines', 'website' => $website, 'range' => $range, 'export' => 'stats.export.search_engines', 'searchEngines' => $searchEngines, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Social Networks stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function socialNetworks(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');
        $websites = $this->getSocialNetworksList();

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'referrer']])
            ->whereIn('value', $websites)
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $socialNetworks = $this->getSocialNetworks($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getSocialNetworks($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getSocialNetworks($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'social-networks', 'website' => $website, 'range' => $range, 'export' => 'stats.export.social_networks', 'socialNetworks' => $socialNetworks, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Campaigns stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function campaigns(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'campaign']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $campaigns = $this->getCampaigns($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getCampaigns($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getCampaigns($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'campaigns', 'website' => $website, 'range' => $range, 'export' => 'stats.export.campaigns', 'campaigns' => $campaigns, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Continents stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function continents(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'continent']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $continents = $this->getContinents($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getContinents($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getContinents($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'continents', 'website' => $website, 'range' => $range, 'export' => 'stats.export.continents', 'continents' => $continents, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Countries stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function countries(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'country']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $countriesChart = $this->getCountries($website, $range, $search, $searchBy, $sortBy, $sort)
            ->get();

        $countries = $this->getCountries($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getCountries($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getCountries($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'countries', 'website' => $website, 'range' => $range, 'export' => 'stats.export.countries', 'countries' => $countries, 'countriesChart' => $countriesChart, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Cities stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function cities(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'city']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $cities = $this->getCities($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getCities($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getCities($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'cities', 'website' => $website, 'range' => $range, 'export' => 'stats.export.cities', 'cities' => $cities, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Languages stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function languages(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'language']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $languages = $this->getLanguages($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getLanguages($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getLanguages($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'languages', 'website' => $website, 'range' => $range, 'export' => 'stats.export.languages', 'languages' => $languages, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Operating Systems stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function operatingSystems(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'os']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $operatingSystems = $this->getOperatingSystems($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getOperatingSystems($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getOperatingSystems($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'operating-systems', 'website' => $website, 'range' => $range, 'export' => 'stats.export.operating_systems', 'operatingSystems' => $operatingSystems, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Browsers stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function browsers(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'browser']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $browsers = $this->getBrowsers($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getBrowsers($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getBrowsers($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'browsers', 'website' => $website, 'range' => $range, 'export' => 'stats.export.browsers', 'browsers' => $browsers, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Screen Resolutions stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function screenResolutions(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'resolution']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $screenResolutions = $this->getScreenResolutions($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getScreenResolutions($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getScreenResolutions($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'screen-resolutions', 'website' => $website, 'range' => $range, 'export' => 'stats.export.screen_resolutions', 'screenResolutions' => $screenResolutions, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Devices stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function devices(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'device']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $devices = $this->getDevices($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getDevices($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getDevices($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'devices', 'website' => $website, 'range' => $range, 'export' => 'stats.export.devices', 'devices' => $devices, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Show the Events stats page.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function events(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        $total = Stat::selectRaw('SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'event']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->first();

        $events = $this->getEvents($website, $range, $search, $searchBy, $sortBy, $sort)
            ->paginate($perPage)
            ->appends(['from' => $range['from'], 'to' => $range['to'], 'search' => $search, 'search_by' => $searchBy, 'sort_by' => $sortBy, 'sort' => $sort]);

        $first = $this->getEvents($website, $range, $search, $searchBy, 'count', 'desc')
            ->first();

        $last = $this->getEvents($website, $range, $search, $searchBy, 'count', 'asc')
            ->first();

        return view('stats.container', ['view' => 'events', 'website' => $website, 'range' => $range, 'export' => 'stats.export.events', 'events' => $events, 'first' => $first, 'last' => $last, 'total' => $total]);
    }

    /**
     * Export the Pages stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportPages(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Pages'), $range, __('URL'), __('Pageviews'), $this->getPages($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Landing Pages stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportLandingPages(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Landing pages'), $range, __('URL'), __('Visitors'), $this->getLandingPages($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Referrers stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportReferrers(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Referrers'), $range, __('Website'), __('Visitors'), $this->getReferrers($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Search Engines stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportSearchEngines(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Search engines'), $range, __('Website'), __('Visitors'), $this->getSearchEngines($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Social Networks stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportSocialNetworks(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Social networks'), $range, __('Website'), __('Visitors'), $this->getSocialNetworks($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Campaigns stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportCampaigns(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Campaigns'), $range, __('Name'), __('Visitors'), $this->getCampaigns($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Continents stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportContinents(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Continents'), $range, __('Name'), __('Visitors'), $this->getContinents($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Countries stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportCountries(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';
        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) ? $request->input('per_page') : config('settings.paginate');

        return $this->exportCSV($request, $website, __('Countries'), $range, __('Name'), __('Visitors'), $this->getCountries($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Cities stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportCities(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Cities'), $range, __('Name'), __('Visitors'), $this->getCities($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Languages stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportLanguages(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Languages'), $range, __('Name'), __('Visitors'), $this->getLanguages($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Operating Systems stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportOperatingSystems(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Operating systems'), $range, __('Name'), __('Visitors'), $this->getOperatingSystems($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Browsers stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportBrowsers(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Browsers'), $range, __('Name'), __('Visitors'), $this->getBrowsers($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Screen Resolutions stats
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportScreenResolutions(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Screen resolutions'), $range, __('Size'), __('Visitors'), $this->getScreenResolutions($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Devices stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportDevices(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Devices'), $range, __('Type'), __('Visitors'), $this->getDevices($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Export the Events stats.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View|CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    public function exportEvents(Request $request, $id)
    {
        $website = Website::where('domain', $id)->firstOrFail();

        if ($this->statsGuard($website)) {
            return view('stats.password', ['website' => $website]);
        };

        $range = $this->range();
        $search = $request->input('search');
        $searchBy = in_array($request->input('search_by'), ['value']) ? $request->input('search_by') : 'value';
        $sortBy = in_array($request->input('sort_by'), ['count', 'value']) ? $request->input('sort_by') : 'count';
        $sort = in_array($request->input('sort'), ['asc', 'desc']) ? $request->input('sort') : 'desc';

        return $this->exportCSV($request, $website, __('Events'), $range, __('Name'), __('Completions'), $this->getEvents($website, $range, $search, $searchBy, $sortBy, $sort)->get());
    }

    /**
     * Get the Pages.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getPages($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'page']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Landing Pages.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getLandingPages($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'landing_page']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Referrers.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getReferrers($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'referrer'], ['value', '<>', $website->domain], ['value', '<>', '']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Search Engines.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getSearchEngines($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        $websites = $this->getSearchEnginesList();

        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'referrer']])
            ->whereIn('value', $websites)
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Social Networks.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getSocialNetworks($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        $websites = $this->getSocialNetworksList();

        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'referrer']])
            ->whereIn('value', $websites)
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Campaigns.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getCampaigns($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'campaign']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Continents.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getContinents($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'continent']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Countries.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getCountries($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'country']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Cities.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getCities($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'city']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Languages.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getLanguages($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'language']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Operating Systems.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getOperatingSystems($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'os']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Browsers.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getBrowsers($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'browser']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Screen Resolutions.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getScreenResolutions($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'resolution']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Devices.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getDevices($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'device']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the Events.
     *
     * @param $website
     * @param $range
     * @param null $search
     * @param null $sort
     * @return mixed
     */
    private function getEvents($website, $range, $search = null, $searchBy = null, $sortBy = null, $sort = null)
    {
        return Stat::selectRaw('`value`, SUM(`count`) as `count`')
            ->where([['website_id', '=', $website->id], ['name', '=', 'event']])
            ->when($search, function ($query) use ($search, $searchBy) {
                return $query->searchValue($search);
            })
            ->whereBetween('date', [$range['from'], $range['to']])
            ->groupBy('value')
            ->orderBy($sortBy, $sort);
    }

    /**
     * Get the visitors, pageviews, or bounce rate in a formatted way, based on the date range.
     *
     * @param $website
     * @param $range
     * @param $type
     * @return array|int[]
     */
    private function getTraffic($website, $range, $type)
    {
        // If the date range is for a single day
        if ($range['unit'] == 'hour') {
            $rows = Stat::where([['website_id', '=', $website->id], ['name', '=', $type . '_hours']])
                ->whereBetween('date', [$range['from'], $range['to']])
                ->orderBy('date', 'asc')
                ->get();

            $output = ['00' => 0, '01' => 0, '02' => 0, '03' => 0, '04' => 0, '05' => 0, '06' => 0, '07' => 0, '08' => 0, '09' => 0, '10' => 0, '11' => 0, '12' => 0, '13' => 0, '14' => 0, '15' => 0, '16' => 0, '17' => 0, '18' => 0, '19' => 0, '20' => 0, '21' => 0, '22' => 0, '23' => 0];

            // Map the values to each date
            foreach ($rows as $row) {
                $output[$row->value] = $row->count;
            }
        } else {
            $rows = Stat::select([
                DB::raw("date_format(`date`, '" . str_replace(['Y', 'm', 'd'], ['%Y', '%m', '%d'], $range['format']) . "') as `date_result`, SUM(`count`) as `aggregate`")
            ])
                ->where([['website_id', '=', $website->id], ['name', '=', $type]])
                ->whereBetween('date', [$range['from'], $range['to']])
                ->groupBy('date_result')
                ->orderBy('date_result', 'asc')
                ->get();

            $rangeMap = $this->calcAllDates(Carbon::createFromFormat('Y-m-d', $range['from'])->format($range['format']), Carbon::createFromFormat('Y-m-d', $range['to'])->format($range['format']), $range['unit'], $range['format'], 0);

            // Remap the result set, and format the array
            $collection = $rows->mapWithKeys(function ($result) use ($range) {
                return [strval($range['unit'] == 'year' ? $result->date_result : Carbon::parse($result->date_result)->format($range['format'])) => $result->aggregate];
            })->all();

            // Merge the results with the pre-calculated possible time ranges
            $output = array_replace($rangeMap, $collection);
        }
        return $output;
    }

    /**
     * List of Social Networks domains.
     *
     * @return string[]
     */
    private function getSocialNetworksList()
    {
        return ['l.facebook.com', 't.co', 'l.instagram.com', 'out.reddit.com', 'www.youtube.com', 'away.vk.com', 't.umblr.com', 'www.pinterest.com'];
    }

    /**
     * List of Search Engine domains.
     *
     * @return string[]
     */
    private function getSearchEnginesList()
    {
        return ['www.google.com', 'www.google.com', 'www.google.ad', 'www.google.ae', 'www.google.com.af', 'www.google.com.ag', 'www.google.com.ai', 'www.google.al', 'www.google.am', 'www.google.co.ao', 'www.google.com.ar', 'www.google.as', 'www.google.at', 'www.google.com.au', 'www.google.az', 'www.google.ba', 'www.google.com.bd', 'www.google.be', 'www.google.bf', 'www.google.bg', 'www.google.com.bh', 'www.google.bi', 'www.google.bj', 'www.google.com.bn', 'www.google.com.bo', 'www.google.com.br', 'www.google.bs', 'www.google.bt', 'www.google.co.bw', 'www.google.by', 'www.google.com.bz', 'www.google.ca', 'www.google.cd', 'www.google.cf', 'www.google.cg', 'www.google.ch', 'www.google.ci', 'www.google.co.ck', 'www.google.cl', 'www.google.cm', 'www.google.cn', 'www.google.com.co', 'www.google.co.cr', 'www.google.com.cu', 'www.google.cv', 'www.google.com.cy', 'www.google.cz', 'www.google.de', 'www.google.dj', 'www.google.dk', 'www.google.dm', 'www.google.com.do', 'www.google.dz', 'www.google.com.ec', 'www.google.ee', 'www.google.com.eg', 'www.google.es', 'www.google.com.et', 'www.google.fi', 'www.google.com.fj', 'www.google.fm', 'www.google.fr', 'www.google.ga', 'www.google.ge', 'www.google.gg', 'www.google.com.gh', 'www.google.com.gi', 'www.google.gl', 'www.google.gm', 'www.google.gr', 'www.google.com.gt', 'www.google.gy', 'www.google.com.hk', 'www.google.hn', 'www.google.hr', 'www.google.ht', 'www.google.hu', 'www.google.co.id', 'www.google.ie', 'www.google.co.il', 'www.google.im', 'www.google.co.in', 'www.google.iq', 'www.google.is', 'www.google.it', 'www.google.je', 'www.google.com.jm', 'www.google.jo', 'www.google.co.jp', 'www.google.co.ke', 'www.google.com.kh', 'www.google.ki', 'www.google.kg', 'www.google.co.kr', 'www.google.com.kw', 'www.google.kz', 'www.google.la', 'www.google.com.lb', 'www.google.li', 'www.google.lk', 'www.google.co.ls', 'www.google.lt', 'www.google.lu', 'www.google.lv', 'www.google.com.ly', 'www.google.co.ma', 'www.google.md', 'www.google.me', 'www.google.mg', 'www.google.mk', 'www.google.ml', 'www.google.com.mm', 'www.google.mn', 'www.google.ms', 'www.google.com.mt', 'www.google.mu', 'www.google.mv', 'www.google.mw', 'www.google.com.mx', 'www.google.com.my', 'www.google.co.mz', 'www.google.com.na', 'www.google.com.ng', 'www.google.com.ni', 'www.google.ne', 'www.google.nl', 'www.google.no', 'www.google.com.np', 'www.google.nr', 'www.google.nu', 'www.google.co.nz', 'www.google.com.om', 'www.google.com.pa', 'www.google.com.pe', 'www.google.com.pg', 'www.google.com.ph', 'www.google.com.pk', 'www.google.pl', 'www.google.pn', 'www.google.com.pr', 'www.google.ps', 'www.google.pt', 'www.google.com.py', 'www.google.com.qa', 'www.google.ro', 'www.google.ru', 'www.google.rw', 'www.google.com.sa', 'www.google.com.sb', 'www.google.sc', 'www.google.se', 'www.google.com.sg', 'www.google.sh', 'www.google.si', 'www.google.sk', 'www.google.com.sl', 'www.google.sn', 'www.google.so', 'www.google.sm', 'www.google.sr', 'www.google.st', 'www.google.com.sv', 'www.google.td', 'www.google.tg', 'www.google.co.th', 'www.google.com.tj', 'www.google.tl', 'www.google.tm', 'www.google.tn', 'www.google.to', 'www.google.com.tr', 'www.google.tt', 'www.google.com.tw', 'www.google.co.tz', 'www.google.com.ua', 'www.google.co.ug', 'www.google.co.uk', 'www.google.com.uy', 'www.google.co.uz', 'www.google.com.vc', 'www.google.co.ve', 'www.google.vg', 'www.google.co.vi', 'www.google.com.vn', 'www.google.vu', 'www.google.ws', 'www.google.rs', 'www.google.co.za', 'www.google.co.zm', 'www.google.co.zw', 'www.google.cat', 'www.bing.com', 'search.yahoo.com', 'uk.search.yahoo.com', 'de.search.yahoo.com', 'fr.search.yahoo.com', 'es.search.yahoo.com', 'search.aol.co.uk', 'search.aol.com', 'duckduckgo.com', 'www.baidu.com', 'yandex.ru', 'www.ecosia.org', 'search.lycos.com'];
    }

    /**
     * Export data in CSV format.
     *
     * @param $request
     * @param $website
     * @param $title
     * @param $range
     * @param $name
     * @param $count
     * @param $results
     * @return CSV\Writer
     * @throws CSV\CannotInsertRecord
     */
    private function exportCSV($request, $website, $title, $range, $name, $count, $results)
    {
        if ($website->user->cannot('dataExport', ['App\Models\User'])) {
            abort(403);
        }

        $content = CSV\Writer::createFromFileObject(new \SplTempFileObject);

        // Generate the header
        $content->insertOne([__('Website'), $website->domain]);
        $content->insertOne([__('Type'), $title]);
        $content->insertOne([__('Interval'), $range['from'] . ' - ' . $range['to']]);
        $content->insertOne([__('Date'), Carbon::now()->format(__('Y-m-d')) . ' ' . Carbon::now()->format('H:i:s') . ' (' . CarbonTimeZone::create(config('app.timezone'))->toOffsetName() . ')']);
        $content->insertOne([__('URL'), $request->fullUrl()]);
        $content->insertOne([__(' ')]);

        // Generate the summary
        $content->insertOne([__('Visitors'), Stat::where([['website_id', '=', $website->id], ['name', '=', 'visitors']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->sum('count')]);
        $content->insertOne([__('Pageviews'), Stat::where([['website_id', '=', $website->id], ['name', '=', 'pageviews']])
            ->whereBetween('date', [$range['from'], $range['to']])
            ->sum('count')]);
        $content->insertOne([__(' ')]);

        // Generate the content
        $content->insertOne([__($name), __($count)]);
        foreach ($results as $result) {
            $content->insertOne($result->toArray());
        }

        return response((string) $content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Disposition' => 'attachment; filename="' . formatTitle([$website->domain, $title, $range['from'], $range['to'], config('settings.title')]) . '.csv"'
        ]);
    }

    /**
     * Validate the link's password.
     *
     * @param ValidateWebsitePasswordRequest $request
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function validatePassword(ValidateWebsitePasswordRequest $request, $id)
    {
        session([md5($id) => true]);
        return redirect()->back();
    }

    /**
     * Guard the stats pages.
     *
     * @param $website
     * @return bool
     */
    private function statsGuard($website)
    {
        // If the link stats is not set to public
        if ($website->privacy !== 0) {
            $user = Auth::user();

            // If the website's privacy is set to private
            if ($website->privacy == 1) {
                // If the user is not authenticated
                // Or if the user is not the owner of the link and not an admin
                if ($user == null || $user->id != $website->user_id && $user->role != 1) {
                    abort(403);
                }
            }

            // If the website's privacy is set to password
            if ($website->privacy == 2) {
                // If there's no passowrd validation in the current session
                if (!session(md5($website->domain))) {
                    // If the user is not authenticated
                    // Or if the user is not the owner of the link and not an admin
                    if ($user == null || $user->id != $website->user_id && $user->role != 1) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
