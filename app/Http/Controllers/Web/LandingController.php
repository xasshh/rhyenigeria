<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\HeroSlide;
use App\Models\NewsPost;
use App\Models\Setting;
use App\Services\Geo\AreaMap;
use App\Support\Countdown;
use Illuminate\View\View;

/** The public homepage: header slider (event flyers first), election countdown, upcoming events, news and the Nigeria map. */
class LandingController extends Controller
{
    public const MAX_EVENT_SLIDES = 3;

    public function __invoke(AreaMap $map): View
    {
        $electionDate = Setting::electionDate();
        $events = Event::query()->publicUpcoming()->with('state:id,name,code')->limit(6)->get();

        // Upcoming events with a flyer lead the slider (soonest first) until they have passed.
        $eventSlides = $events->filter(fn (Event $e) => $e->flyer_path)->take(self::MAX_EVENT_SLIDES)->map->toSlide();

        return view('landing', [
            'slides' => $eventSlides->concat(HeroSlide::query()->shown()->get())->values(),
            'events' => $events,
            'electionDate' => $electionDate,
            'electionLabel' => Setting::electionLabel(),
            'countdown' => Countdown::parts($electionDate),
            'map' => $map->states(),
            'news' => NewsPost::query()->publicStories()->with('photos')->latest('published_at')->limit(3)->get(),
        ]);
    }
}
