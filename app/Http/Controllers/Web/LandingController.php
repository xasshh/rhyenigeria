<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\HeroSlide;
use App\Models\NewsPost;
use App\Models\Setting;
use App\Models\State;
use App\Models\XPost;
use App\Services\Geo\AreaMap;
use App\Support\Countdown;
use Illuminate\View\View;

/** The public homepage: header slider (upcoming events first), election countdown, news and the Nigeria map. */
class LandingController extends Controller
{
    public const MAX_EVENT_SLIDES = 6;

    public function __invoke(AreaMap $map): View
    {
        $electionDate = Setting::electionDate();
        // Every upcoming public event leads the slider (soonest first) until it has passed.
        $eventSlides = Event::query()->publicUpcoming()->with('state:id,name,code')
            ->limit(self::MAX_EVENT_SLIDES)->get()->map->toSlide();

        return view('landing', [
            'slides' => $eventSlides->concat(HeroSlide::query()->shown()->get())->values(),
            'electionDate' => $electionDate,
            'electionLabel' => Setting::electionLabel(),
            'xPosts' => XPost::query()->newest()->limit(XPost::HOMEPAGE_MAX)->get(),
            'xHandle' => Admin\XPostController::siteHandle(),
            'countdown' => Countdown::parts($electionDate),
            // A single-state site maps that state's LGAs instead of the country.
            'siteState' => $siteState = site_state_code() ? State::where('code', site_state_code())->first() : null,
            'map' => $siteState ? $map->lgas($siteState) : $map->states(),
            'news' => NewsPost::query()->publicStories()->with('photos')->latest('published_at')->limit(3)->get(),
        ]);
    }
}
