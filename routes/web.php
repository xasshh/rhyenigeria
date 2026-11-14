<?php

use App\Http\Controllers\Api\GeographyController;
use App\Http\Controllers\Web\Admin\CoordinatorController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\SettingsController;
use App\Http\Controllers\Web\Admin\SlideController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\FieldWorkController;
use App\Http\Controllers\Web\JoinController;
use App\Http\Controllers\Web\LandingController;
use App\Http\Controllers\Web\MapController;
use App\Http\Controllers\Web\MembershipController;
use App\Http\Controllers\Web\NewsController;
use App\Http\Controllers\Web\PublicController;
use App\Http\Controllers\Web\Staff\CanvasserController;
use App\Http\Controllers\Web\Staff\DashboardController;
use App\Http\Controllers\Web\Staff\EmpowermentController;
use App\Http\Controllers\Web\Staff\LiveMapController;
use App\Http\Controllers\Web\Staff\MemberController;
use App\Http\Controllers\Web\Staff\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('privacy', [PublicController::class, 'privacy'])->name('privacy');
Route::get('news', [NewsController::class, 'index'])->name('news.index');
Route::get('news/{slug}', [NewsController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('news.show');
Route::get('verify/{token}', [PublicController::class, 'verify'])->middleware('throttle:60,1')->name('verify');

// Public geography lookups for the sign-up form (the API versions need a token).
Route::middleware(['throttle:geo', 'cache.headers:public;max_age=600'])->prefix('geo')->group(function () {
    Route::get('states/{state}/lgas', [GeographyController::class, 'lgas'])->name('geo.lgas');
    Route::get('lgas/{lga}/wards', [GeographyController::class, 'wards'])->name('geo.wards');
    Route::get('wards/{ward}/polling-units', [GeographyController::class, 'pollingUnits'])->name('geo.polling-units');
});

// Homepage map: members per state (aggregate counts only), cached.
Route::get('map/states.json', [MapController::class, 'states'])->middleware('throttle:geo')->name('map.states');

// Sign-up.
Route::get('join', [JoinController::class, 'show'])->name('join');
Route::post('join/check/{step}', [JoinController::class, 'check'])->middleware(['guest', 'throttle:join-check'])->name('join.check');
Route::post('join', [JoinController::class, 'store'])->middleware(['guest', 'throttle:join'])->name('join.store');

// Sign-in.
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'reset'])->middleware('throttle:password-reset')->name('password.update');
});
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    // The homepage "Empowerment" button. Guests are sent to sign in first and come back here.
    Route::get('empowerment', [PublicController::class, 'empowerment'])->name('empowerment');

    // Empowerment photos: the member themselves, or staff who can see that member.
    Route::get('empowerment/{report}/photo', [EmpowermentController::class, 'photo'])->name('empowerment.photo');

    // Members (including canvassers).
    Route::middleware('role:member')->prefix('membership')->name('membership.')->controller(MembershipController::class)->group(function () {
        Route::get('/', 'home')->name('home');
        Route::get('welcome', 'welcome')->name('welcome');
        Route::get('card', 'card')->name('card');
        Route::put('card/details', 'updateDetails')->name('details.update');
        Route::put('card/identity', 'updateIdentity')->name('identity.update');
        Route::put('card/password', 'updatePassword')->name('password.update');
        Route::post('canvasser', 'applyCanvasser')->name('canvasser.apply');
        Route::get('events', 'events')->name('events');
        Route::get('empowerment', 'empowerment')->name('empowerment');
        Route::post('empowerment', 'storeEmpowerment')->middleware('throttle:20,60')->name('empowerment.store');
        Route::get('campaign-dp', 'dp')->name('dp');
    });

    // Field work for approved canvassers: location sharing only between Start shift and End shift.
    Route::middleware('role:member')->prefix('membership/field')->name('membership.field.')->controller(FieldWorkController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('consent', 'acceptConsent')->name('consent.accept');
        Route::delete('consent', 'withdrawConsent')->name('consent.withdraw');
        Route::post('shift', 'startShift')->name('shift.start');
        Route::delete('shift', 'endShift')->name('shift.end');
        Route::post('locations', 'locations')->middleware('throttle:locations')->name('locations');
        Route::post('activities', 'storeActivity')->middleware('throttle:60,1')->name('activities.store');
    });
    Route::get('field/activities/{activity}/photo', [FieldWorkController::class, 'photo'])->name('field.activities.photo');

    // Pages coordinators and super admins share. Every query is scoped to the viewer.
    $staffRoutes = function () {
        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::get('members/export', [MemberController::class, 'export'])->middleware('throttle:10,1')->name('members.export');
        Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::get('canvassers', [CanvasserController::class, 'index'])->name('canvassers.index');
        Route::post('canvassers/{member}', [CanvasserController::class, 'decide'])->name('canvassers.decide');
        Route::get('empowerment', [EmpowermentController::class, 'index'])->name('empowerment.index');
        Route::get('posts', [PostController::class, 'index'])->name('posts.index');
        Route::post('posts/events', [PostController::class, 'storeEvent'])->name('events.store');
        Route::put('posts/events/{event}', [PostController::class, 'updateEvent'])->name('events.update');
        Route::put('posts/news/{news}', [PostController::class, 'updateNews'])->name('news.update');
        Route::delete('posts/news/{news}/photos/{photo}', [PostController::class, 'destroyPhoto'])->name('news.photos.destroy');
        Route::get('map/lgas', [MapController::class, 'lgas'])->name('map.lgas');
        Route::get('live', [LiveMapController::class, 'index'])->name('live');
        Route::get('live/feed', [LiveMapController::class, 'feed'])->name('live.feed');
        Route::get('live/{member}/trail', [LiveMapController::class, 'trail'])->name('live.trail');
        Route::delete('posts/events/{event}', [PostController::class, 'destroyEvent'])->name('events.destroy');
        Route::post('posts/news', [PostController::class, 'storeNews'])->name('news.store');
        Route::delete('posts/news/{news}', [PostController::class, 'destroyNews'])->name('news.destroy');
    };

    Route::middleware('role:coordinator')->prefix('coordinator')->name('coordinator.')->group(function () use ($staffRoutes) {
        Route::get('/', [DashboardController::class, 'coordinator'])->name('dashboard');
        $staffRoutes();
    });

    Route::middleware('role:super_admin')->prefix('admin')->name('admin.')->group(function () use ($staffRoutes) {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('states/{state}', [DashboardController::class, 'state'])->name('states.show');
        Route::get('coordinators', [CoordinatorController::class, 'index'])->name('coordinators.index');
        Route::post('coordinators', [CoordinatorController::class, 'store'])->name('coordinators.store');
        Route::patch('coordinators/{coordinator}', [CoordinatorController::class, 'update'])->name('coordinators.update');
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::resource('slides', SlideController::class)->except('show');
        Route::patch('slides/{slide}/toggle', [SlideController::class, 'toggle'])->name('slides.toggle');
        $staffRoutes();
    });
});
