<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Support\WebImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin → Homepage slides: the header slider on the landing page. */
class SlideController extends Controller
{
    public function index(): View
    {
        return view('admin.slides.index', ['slides' => HeroSlide::query()->orderBy('position')->orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('admin.slides.form', ['slide' => new HeroSlide(['is_active' => false, 'layout' => HeroSlide::LAYOUT_IMAGE, 'position' => (int) HeroSlide::max('position') + 1])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $slide = new HeroSlide;
        $this->save($request, $slide);

        return redirect()->route('admin.slides.index')->with('status', 'Slide added.');
    }

    public function edit(HeroSlide $slide): View
    {
        return view('admin.slides.form', ['slide' => $slide]);
    }

    public function update(Request $request, HeroSlide $slide): RedirectResponse
    {
        $this->save($request, $slide);

        return redirect()->route('admin.slides.index')->with('status', 'Slide saved.');
    }

    /** Quick on/off from the list. */
    public function toggle(HeroSlide $slide): RedirectResponse
    {
        $slide->is_active = ! $slide->is_active;
        $slide->save();

        return back()->with('status', $slide->is_active ? 'Slide is now on the homepage.' : 'Slide hidden from the homepage.');
    }

    public function destroy(HeroSlide $slide): RedirectResponse
    {
        $slide->deleteUploadedImages();
        $slide->delete();

        return redirect()->route('admin.slides.index')->with('status', 'Slide deleted.');
    }

    private function save(Request $request, HeroSlide $slide): void
    {
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
            'layout' => ['required', Rule::in(array_keys(HeroSlide::LAYOUTS))],
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'headline' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:400'],
            'button_label' => ['nullable', 'string', 'max:40', 'required_with:button_url'],
            // A path on this site (/join) or a full https:// address.
            'button_url' => ['nullable', 'string', 'max:255', 'required_with:button_label', 'regex:#^(/[^\s]*|https://[^\s]+)$#'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=600'],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'remove_image' => ['boolean'],
        ], ['button_url.regex' => 'Use a path on this site such as /join, or a full https:// link.']);

        $slide->fill($data);
        $slide->is_active = $request->boolean('is_active');

        if ($request->boolean('remove_image') || $request->hasFile('image')) {
            $slide->deleteUploadedImages();
            $slide->image_path = null;
            $slide->image_original_path = null;
        }

        if ($request->hasFile('image')) {
            [$slide->image_original_path, $slide->image_path] = WebImage::storeWithWebp($request->file('image'), 'slides', 1600);
        }

        $slide->save();
    }
}
