<?php

use App\Http\Controllers\Profile\ContactCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('profile/contact-center')
    ->name('profile.contacts.')
    ->group(function (): void {
        Route::get('/', [ContactCenterController::class, 'index'])->name('index');

        Route::post('/contact-points', [ContactCenterController::class, 'storeContact'])
            ->name('contact.store');
        Route::put('/contact-points/{contactPoint}', [ContactCenterController::class, 'updateContact'])
            ->name('contact.update');
        Route::delete('/contact-points/{contactPoint}', [ContactCenterController::class, 'destroyContact'])
            ->name('contact.destroy');

        Route::post('/addresses', [ContactCenterController::class, 'storeAddress'])
            ->name('address.store');
        Route::put('/addresses/{address}', [ContactCenterController::class, 'updateAddress'])
            ->name('address.update');
        Route::delete('/addresses/{address}', [ContactCenterController::class, 'destroyAddress'])
            ->name('address.destroy');
    });
