<?php

declare(strict_types=1);

use App\Http\Controllers\Central\Web\Me\PasskeyController;
use App\Http\Controllers\Central\Web\Me\ProfileController;
use App\Http\Controllers\Central\Web\Me\SecurityController;
use App\Http\Controllers\Central\Web\Me\TfaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Me Routes (User Account Management)
|--------------------------------------------------------------------------
|
| These routes handle the user's own account management pages like
| profile settings and security settings.
|
*/

Route::middleware(['auth', 'verified'])->prefix('me')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show'])->name('me.profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('me.profile.update');
    Route::get('/profile-picture', [ProfileController::class, 'showProfilePicture'])->name('me.profile-picture.show');
    Route::post('/profile-picture', [ProfileController::class, 'updateProfilePicture'])->name('me.profile-picture.update');
    Route::delete('/profile-picture', [ProfileController::class, 'removeProfilePicture'])->name('me.profile-picture.destroy');

    Route::get('/security', [SecurityController::class, 'show'])->name('me.security');
    Route::put('/password', [SecurityController::class, 'update'])->name('me.password.update');

    Route::middleware('password.confirm')->group(function (): void {
        Route::prefix('tfa')->group(function (): void {
            Route::post('/enable', [TfaController::class, 'enable'])->name('me.tfa.enable');
            Route::get('/qr-code', [TfaController::class, 'qrCode'])->name('me.tfa.qr-code');
            Route::get('/secret-key', [TfaController::class, 'secretKey'])->name('me.tfa.secret-key');
            Route::post('/confirm', [TfaController::class, 'confirm'])->name('me.tfa.confirm');
            Route::get('/recovery-codes', [TfaController::class, 'recoveryCodes'])->name('me.tfa.recovery-codes');
            Route::post('/recovery-codes/regenerate', [TfaController::class, 'regenerateRecoveryCodes'])->name('me.tfa.recovery-codes.regenerate');
            Route::post('/disable', [TfaController::class, 'disable'])->name('me.tfa.disable');
        });

        Route::prefix('passkeys')->group(function (): void {
            Route::get('/', [PasskeyController::class, 'index'])->name('me.passkeys.index');
            Route::post('/register/options', [PasskeyController::class, 'registerOptions'])->name('me.passkeys.register.options');
            Route::post('/register', [PasskeyController::class, 'store'])->name('me.passkeys.register');
            Route::delete('/{credential}', [PasskeyController::class, 'destroy'])->name('me.passkeys.destroy');
        });
    });

    Route::get('/confirmed-password-status', [SecurityController::class, 'confirmationStatus'])->name('me.confirmed-password-status');
    Route::post('/confirm-password', [SecurityController::class, 'confirmPassword'])->name('me.confirm-password');
});
