<?php

use App\Http\Controllers\PublicIntake\AdoptRealEstatePortalController;
use App\Http\Controllers\PublicIntake\PublicRealEstateAdminController;
use App\Http\Controllers\PublicIntake\PublicRealEstateIntakeController;
use App\Http\Controllers\PublicIntake\PublicRealEstateMediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('office')
    ->name('public.real-estate.')
    ->group(function (): void {
        Route::get('/case/{case}/preview', [PublicRealEstateIntakeController::class, 'preview'])
            ->where('case', 'RE-[A-Z0-9]{10}')
            ->middleware('throttle:20,1')
            ->name('preview');

        Route::get('/{portal}', [PublicRealEstateIntakeController::class, 'show'])
            ->where('portal', '[A-Za-z0-9]{48}')
            ->middleware('throttle:60,1')
            ->name('show');

        Route::post('/{portal}/cases', [PublicRealEstateIntakeController::class, 'store'])
            ->where('portal', '[A-Za-z0-9]{48}')
            ->middleware('throttle:6,1')
            ->name('store');
    });

Route::middleware('auth')
    ->prefix('office-admin')
    ->name('office.real-estate.')
    ->group(function (): void {
        Route::get('/{portal:uuid}/cases', [PublicRealEstateAdminController::class, 'index'])
            ->name('index');

        Route::post('/{portal:uuid}/adopt-business', AdoptRealEstatePortalController::class)
            ->name('adopt-business');

        Route::get('/{portal:uuid}/cases/{case}', [PublicRealEstateAdminController::class, 'show'])
            ->where('case', 'RE-[A-Z0-9]{10}')
            ->name('show');

        Route::patch('/{portal:uuid}/cases/{case}/status', [PublicRealEstateAdminController::class, 'updateStatus'])
            ->where('case', 'RE-[A-Z0-9]{10}')
            ->name('status');

        Route::post('/{portal:uuid}/cases/{case}/media', [PublicRealEstateMediaController::class, 'store'])
            ->where('case', 'RE-[A-Z0-9]{10}')
            ->name('media.store');

        Route::get('/{portal:uuid}/cases/{case}/media/{media}', [PublicRealEstateMediaController::class, 'stream'])
            ->where('case', 'RE-[A-Z0-9]{10}')
            ->whereNumber('media')
            ->name('media.stream');

        Route::delete('/{portal:uuid}/cases/{case}/media/{media}', [PublicRealEstateMediaController::class, 'destroy'])
            ->where('case', 'RE-[A-Z0-9]{10}')
            ->whereNumber('media')
            ->name('media.destroy');

    });
