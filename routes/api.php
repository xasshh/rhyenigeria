<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsentController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\GeographyController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    // Geography lookups.
    Route::get('states', [GeographyController::class, 'states']);
    Route::get('states/{state}/lgas', [GeographyController::class, 'lgas']);
    Route::get('lgas/{lga}/wards', [GeographyController::class, 'wards']);
    Route::get('wards/{ward}/polling-units', [GeographyController::class, 'pollingUnits']);

    // Field agents: consent, shifts, location reporting, data collection.
    Route::middleware('role:field_agent')->group(function () {
        Route::get('consent/tracking', [ConsentController::class, 'show']);
        Route::post('consent/tracking', [ConsentController::class, 'accept']);
        Route::delete('consent/tracking', [ConsentController::class, 'withdraw']);

        Route::get('shifts/current', [ShiftController::class, 'current']);
        Route::post('shifts/start', [ShiftController::class, 'start']);
        Route::post('shifts/end', [ShiftController::class, 'end']);

        Route::post('locations', [LocationController::class, 'store'])
            ->withoutMiddleware('throttle:api')
            ->middleware('throttle:locations');

        Route::post('submissions', [SubmissionController::class, 'store']);
        Route::post('submissions/{submission}/photos', [SubmissionController::class, 'addPhoto']);
    });

    // Live map. Results are always limited to what the requester may see.
    Route::get('map/positions', [MapController::class, 'positions']);
    Route::get('agents/{agent}/trail', [MapController::class, 'trail']);

    // Collected data.
    Route::get('forms', [FormController::class, 'index']);
    Route::get('submissions/export', [SubmissionController::class, 'export'])->middleware('role:super_admin,coordinator');
    Route::get('submissions', [SubmissionController::class, 'index']);
    Route::get('submissions/{submission}', [SubmissionController::class, 'show']);
    Route::get('submissions/{submission}/photos/{photo}', [SubmissionController::class, 'photo'])->name('submissions.photos.show');

    // People. Listing is scoped; changes are for super admins only.
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);

    Route::middleware('role:super_admin')->group(function () {
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{user}', [UserController::class, 'update']);

        Route::post('forms', [FormController::class, 'store']);
        Route::patch('forms/{form}', [FormController::class, 'update']);

        Route::get('audit-logs', [AuditLogController::class, 'index']);
    });
});
