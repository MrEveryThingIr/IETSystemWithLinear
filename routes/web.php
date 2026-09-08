<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\GroupInvitationController;
use App\Livewire\Actors\Create;
use App\Livewire\Actors\Edit;
use App\Livewire\Actors\Index;
use App\Livewire\Actors\Show;
use App\Livewire\Administration\Index as AdministrationIndex;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmailNotice;
use App\Livewire\Groups\Create as CreateGroup;
use App\Livewire\Groups\Index as GroupIndex;
use App\Livewire\Groups\Show as GroupShow;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));
Route::get('/invitations/{token}', [GroupInvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [GroupInvitationController::class, 'accept'])->middleware(['auth', 'account.active', 'verified'])->name('invitations.accept');

Route::middleware('guest')->group(function (): void {
    Route::livewire('/register', Register::class)->name('register');
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::livewire('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');
Route::middleware(['auth', 'account.active'])->group(function (): void {
    Route::livewire('/email/verify', VerifyEmailNotice::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::view('/dashboard', 'dashboard')->middleware('verified')->name('dashboard');
});

Route::middleware(['auth', 'account.active', 'verified'])->group(function (): void {
    Route::livewire('/groups', GroupIndex::class)->name('groups.index');
    Route::livewire('/groups/create', CreateGroup::class)->name('groups.create');
    Route::livewire('/groups/{group}', GroupShow::class)->name('groups.show');

    Route::middleware('global.permission:actors.manage')->group(function (): void {
        Route::livewire('/actors', Index::class)->name('actors.index');
        Route::livewire('/actors/create', Create::class)->name('actors.create');
        Route::livewire('/actors/{actor}', Show::class)->name('actors.show');
        Route::livewire('/actors/{actor}/edit', Edit::class)->name('actors.edit');
    });

    Route::livewire('/administration', AdministrationIndex::class)->middleware('global.permission:rbac.manage')->name('administration.index');
});
