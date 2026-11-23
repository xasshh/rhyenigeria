<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\XPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Admin → X posts: paste the link of a post on X and it shows on the homepage. */
class XPostController extends Controller
{
    public function index(): View
    {
        return view('admin.x-posts', [
            'posts' => XPost::query()->newest()->with('addedBy:id,name')->paginate(30),
            'handle' => self::siteHandle(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['link' => ['required', 'string', 'max:500']]);
        $added = 0;

        // One link per line, so several posts can be added at once.
        foreach (preg_split('/\s+/', trim($request->input('link'))) as $link) {
            $parsed = XPost::parseLink($link);
            if ($parsed === null) {
                throw ValidationException::withMessages(['link' => 'That is not a link to a post on X. Open the post, tap Share → Copy link, and paste it here.']);
            }
            $post = XPost::firstOrCreate(['status_id' => $parsed['status_id']], [
                'handle' => $parsed['handle'],
                'posted_at' => XPost::timeFromId($parsed['status_id']),
                'added_by' => $request->user()->id,
            ]);
            $added += (int) $post->wasRecentlyCreated;
        }

        return back()->with('status', $added ? ($added === 1 ? 'Post added to the homepage.' : "{$added} posts added to the homepage.") : 'That post is already on the homepage.');
    }

    public function destroy(XPost $xPost): RedirectResponse
    {
        $xPost->delete();

        return back()->with('status', 'Post removed from the homepage.');
    }

    /** The site's own X account, from its profile link in config/site.php. */
    public static function siteHandle(): ?string
    {
        return preg_match('#(?:x|twitter)\.com/([A-Za-z0-9_]{1,15})#i', (string) config('site.social.x'), $m) ? $m[1] : null;
    }
}
