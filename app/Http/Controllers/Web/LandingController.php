<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Models\NewsPost;
use App\Models\Setting;
use App\Services\Geo\AreaMap;
use App\Support\Countdown;
use Illuminate\View\View;

/** The public homepage: header slider, election countdown and the Nigeria map. */
class LandingController extends Controller
{
    public function __invoke(AreaMap $map): View
    {
        $electionDate = Setting::electionDate();

        return view('landing', [
            'slides' => HeroSlide::query()->shown()->get(),
            'electionDate' => $electionDate,
            'electionLabel' => Setting::electionLabel(),
            'countdown' => Countdown::parts($electionDate),
            'map' => $map->states(),
            'news' => NewsPost::query()->publicStories()->with('photos')->latest('published_at')->limit(3)->get(),
        ]);
    }
}
