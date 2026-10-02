<?php

use App\Http\Controllers\Admin\FeatureSurfaceAdminController;
use App\Http\Controllers\Workspace\WorkspaceController;
use App\Http\Middleware\RequireFeatureSurface;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'account.active', 'verified'])->group(function (): void {
    Route::get('/workspace', [WorkspaceController::class, 'index'])
        ->name('workspace.index');

    Route::get('/workspace/real-estate', fn () => redirect()->route('businesses.index', ['kind' => 'real_estate']))
        ->middleware(RequireFeatureSurface::class.':business')
        ->name('workspace.real-estate.index');

    Route::prefix('platform/publication')
        ->name('platform.publication.')
        ->group(function (): void {
            Route::get('/', [FeatureSurfaceAdminController::class, 'index'])->name('index');
            Route::get('/users/{user}', [FeatureSurfaceAdminController::class, 'edit'])->name('edit');
            Route::put('/users/{user}', [FeatureSurfaceAdminController::class, 'update'])->name('update');
        });

    Route::redirect('/admin/surfaces', '/platform/publication');
});
