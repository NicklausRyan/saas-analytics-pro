<?php

namespace App\Http\View\Composers;

use App\Models\Website;
use App\Models\Stat;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class UserStatsComposer
{
    /**
     * @var
     */
    private $pageviewsCount;

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        if (Auth::check()) {
            $user = Auth::user();

            $now = Carbon::now();

            if (!$this->pageviewsCount) {
                $this->pageviewsCount = Stat::where('name', '=', 'pageviews')
                    ->whereIn('website_id', Website::select('id')->where('user_id', '=', $user->id))
                    ->whereBetween('date', [(clone $now)->startOfMonth(), (clone $now)->endOfMonth()])
                    ->sum('count');
            }

            $view->with('pageviewsCount', $this->pageviewsCount);
        }
    }
}
