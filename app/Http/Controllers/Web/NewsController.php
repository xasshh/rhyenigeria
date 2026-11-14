<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use Illuminate\View\View;

/** Public news and photo galleries: only posts a super admin ticked "Show on the public website". */
class NewsController extends Controller
{
    public function index(): View
    {
        return view('news.index', [
            'posts' => NewsPost::query()->publicStories()->with('photos')->latest('published_at')->paginate(12),
        ]);
    }

    public function show(string $slug): View
    {
        $post = NewsPost::query()->publicStories()->where('slug', $slug)->with('photos')->firstOrFail();

        return view('news.show', [
            'post' => $post,
            'more' => NewsPost::query()->publicStories()->whereKeyNot($post->id)->with('photos')->latest('published_at')->limit(3)->get(),
        ]);
    }
}
