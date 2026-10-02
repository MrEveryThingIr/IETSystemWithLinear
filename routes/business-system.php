<?php

use App\Http\Controllers\Business\BusinessCatalogController;
use App\Http\Controllers\Business\BusinessClientController;
use App\Http\Controllers\Business\BusinessContactController;
use App\Http\Controllers\Business\BusinessController;
use App\Http\Controllers\Business\BusinessTeamController;
use App\Http\Controllers\Profile\ProfessionProfileController;
use App\Http\Middleware\RequireFeatureSurface;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'account.active', 'verified'])->group(function (): void {
    Route::middleware(RequireFeatureSurface::class.':business')->group(function (): void {
        Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
        Route::get('/businesses/create', [BusinessController::class, 'create'])->name('businesses.create');
        Route::post('/businesses', [BusinessController::class, 'store'])->name('businesses.store');
        Route::get('/businesses/{business}', [BusinessController::class, 'show'])->name('businesses.show');
        Route::put('/businesses/{business}', [BusinessController::class, 'update'])->name('businesses.update');

        Route::get('/businesses/{business}/clients', [BusinessClientController::class, 'index'])->name('businesses.clients.index');
        Route::post('/businesses/{business}/clients', [BusinessClientController::class, 'store'])->name('businesses.clients.store');
        Route::patch('/businesses/{business}/clients/{client}/archive', [BusinessClientController::class, 'archive'])->name('businesses.clients.archive');

        Route::get('/businesses/{business}/catalog', [BusinessCatalogController::class, 'index'])->name('businesses.catalog.index');
        Route::post('/businesses/{business}/catalog/categories', [BusinessCatalogController::class, 'storeCategory'])->name('businesses.catalog.categories.store');
        Route::post('/businesses/{business}/catalog/listings', [BusinessCatalogController::class, 'store'])->name('businesses.catalog.listings.store');
        Route::post('/businesses/{business}/catalog/listings/{listing}/publish', [BusinessCatalogController::class, 'publish'])->name('businesses.catalog.listings.publish');
        Route::post('/businesses/{business}/real-estate/{portal:uuid}/cases/{case}/promote', [BusinessCatalogController::class, 'promoteRealEstateCase'])
            ->name('businesses.real-estate.cases.promote');

        Route::post('/businesses/{business}/contacts', [BusinessContactController::class, 'storeContact'])->name('businesses.contacts.store');
        Route::delete('/businesses/{business}/contacts/{contactPoint}', [BusinessContactController::class, 'destroyContact'])->name('businesses.contacts.destroy');
        Route::post('/businesses/{business}/addresses', [BusinessContactController::class, 'storeAddress'])->name('businesses.addresses.store');
        Route::delete('/businesses/{business}/addresses/{address}', [BusinessContactController::class, 'destroyAddress'])->name('businesses.addresses.destroy');

        Route::post('/businesses/{business}/members', [BusinessTeamController::class, 'store'])->name('businesses.members.store');
        Route::put('/businesses/{business}/members/{membership}', [BusinessTeamController::class, 'update'])->name('businesses.members.update');
        Route::delete('/businesses/{business}/members/{membership}', [BusinessTeamController::class, 'destroy'])->name('businesses.members.destroy');
        Route::post('/businesses/{business}/transfer-ownership', [BusinessTeamController::class, 'transferOwnership'])->name('businesses.transfer-ownership');

        Route::post('/businesses/{business}/members/{membership}/professions', [BusinessTeamController::class, 'assignProfession'])->name('businesses.members.professions.store');
        Route::delete('/businesses/{business}/members/{membership}/professions/{profession}', [BusinessTeamController::class, 'removeProfession'])->name('businesses.members.professions.destroy');
    });

    Route::middleware(RequireFeatureSurface::class.':profile')->group(function (): void {
        Route::get('/profile/professions', [ProfessionProfileController::class, 'index'])->name('profile.professions.index');
        Route::post('/profile/professions', [ProfessionProfileController::class, 'store'])->name('profile.professions.store');
        Route::delete('/profile/professions/{actorProfession}', [ProfessionProfileController::class, 'destroy'])->name('profile.professions.destroy');
    });
});
