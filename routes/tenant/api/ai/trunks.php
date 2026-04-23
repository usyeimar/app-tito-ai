<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\API\Agent\TrunkController;
use Illuminate\Support\Facades\Route;

Route::prefix('trunks')->name('trunks.')->group(function () {
    Route::get('/', [TrunkController::class, 'index'])->name('index');
    Route::post('/', [TrunkController::class, 'store'])->name('store');
    Route::get('{trunk}', [TrunkController::class, 'show'])->name('show');
    Route::patch('{trunk}', [TrunkController::class, 'update'])->name('update');
    Route::delete('{trunk}', [TrunkController::class, 'destroy'])->name('destroy');
});
