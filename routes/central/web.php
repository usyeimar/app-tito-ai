<?php

use App\Http\Controllers\Central\Web\Tenancy\TenantController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('workspaces', [TenantController::class, 'index'])->name('workspaces');
    Route::post('workspaces', [TenantController::class, 'store'])->name('workspaces.store');
    Route::get('workspaces/{tenant:slug}/enter', [TenantController::class, 'enter'])->name('workspaces.enter');
});

Route::middleware(['auth'])->group(function () {
    Route::get('workspaces/{tenant:slug}/change', [TenantController::class, 'enter'])->name('workspaces.change');
});

require __DIR__.'/web/settings.php';
require __DIR__.'/web/me.php';
