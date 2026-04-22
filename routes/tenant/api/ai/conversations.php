<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\API\Agent\ConversationController;
use Illuminate\Support\Facades\Route;

Route::prefix('conversations')->name('conversations.')
    ->group(function () {
        Route::get('/', [ConversationController::class, 'index'])->name('index');
        Route::get('{conversation}', [ConversationController::class, 'show'])->name('show');
        Route::get('{conversation}/transcripts', [ConversationController::class, 'transcripts'])->name('transcripts');
        Route::delete('{conversation}', [ConversationController::class, 'destroy'])->name('destroy');
    });
