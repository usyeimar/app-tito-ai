<?php

use App\Http\Controllers\Central\API\Auth\Authentication\AuthenticationController;
use App\Http\Controllers\Central\API\Auth\EmailVerification\EmailVerificationController;
use App\Http\Controllers\Central\API\Auth\Impersonation\TenantImpersonationController;
use App\Http\Controllers\Central\API\Auth\Invitation\InvitationController as AuthInvitationController;
use App\Http\Controllers\Central\API\Auth\Passkey\PasskeyController;
use App\Http\Controllers\Central\API\Auth\Passkey\PasskeyLoginController;
use App\Http\Controllers\Central\API\Auth\Passkey\PasskeyRegistrationController;
use App\Http\Controllers\Central\API\Auth\Password\PasswordController;
use App\Http\Controllers\Central\API\Auth\Profile\ProfileController;
use App\Http\Controllers\Central\API\Auth\SocialLogin\GoogleSocialLoginController;
use App\Http\Controllers\Central\API\Auth\SocialLogin\MicrosoftSocialLoginController;
use App\Http\Controllers\Central\API\Auth\Tfa\TfaController;
use App\Http\Controllers\Central\API\Auth\Token\TokenController as AuthTokenController;
use App\Http\Controllers\Central\API\Tenancy\TenantController;
use App\Http\Controllers\Shared\ProfilePictureController;
use Illuminate\Support\Facades\Route;

Route::post('refresh', [AuthTokenController::class, 'refresh'])->middleware('throttle:auth.refresh');

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthenticationController::class, 'register'])->middleware('throttle:auth.register');
    Route::post('login', [AuthenticationController::class, 'login'])->middleware('throttle:auth.login');
    Route::post('tfa/verify', [TfaController::class, 'verifyTfa'])->middleware('throttle:auth.tfa_verify');
    Route::post('forgot-password', [PasswordController::class, 'forgotPassword'])->middleware('throttle:auth.forgot_password');
    Route::post('reset-password', [PasswordController::class, 'resetPassword']);
    Route::post('google', [GoogleSocialLoginController::class, 'login'])->middleware('throttle:auth.social_login');
    Route::post('microsoft', [MicrosoftSocialLoginController::class, 'login'])->middleware('throttle:auth.social_login');

    Route::prefix('passkeys')->group(function () {
        Route::post('login/options', [PasskeyLoginController::class, 'getOptions'])->middleware('throttle:auth.passkeys');
        Route::post('login', [PasskeyLoginController::class, 'login'])->middleware('throttle:auth.passkeys');

        Route::middleware('auth:central-api')->group(function () {
            Route::post('register/options', [PasskeyRegistrationController::class, 'options'])->middleware('throttle:auth.passkeys');
            Route::post('register', [PasskeyRegistrationController::class, 'store'])->middleware('throttle:auth.passkeys');
        });
    });

    Route::get('invitations/resolve', [AuthInvitationController::class, 'resolve'])
        ->name('auth.invitations.resolve')
        ->middleware('signed');

    Route::middleware(['auth:central-api'])->group(function () {
        Route::prefix('me')->group(function () {
            Route::get('/', [AuthenticationController::class, 'me']);
            Route::patch('/', [ProfileController::class, 'update']);
            Route::post('/profile-picture', [ProfileController::class, 'updateProfilePicture']);
            Route::delete('/profile-picture', [ProfileController::class, 'removeProfilePicture']);
            Route::patch('/password', [ProfileController::class, 'updatePassword']);
        });

        Route::post('logout', [AuthenticationController::class, 'logout']);
        Route::post('confirm-password', [PasswordController::class, 'confirmPassword']);
        Route::post('impersonate', [TenantImpersonationController::class, 'create']);
        Route::get('tokens', [AuthTokenController::class, 'tokens']);
        Route::delete('tokens', [AuthTokenController::class, 'revokeTokens']);
        Route::delete('tokens/{token}', [AuthTokenController::class, 'revokeToken']);
        Route::get('passkeys', [PasskeyController::class, 'passkeys']);
        Route::delete('passkeys/{credential}', [PasskeyController::class, 'revokePasskey']);

        Route::post('email/verification-notification', [EmailVerificationController::class, 'resendEmailVerification'])->middleware('throttle:auth.email_verification');
        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verifyEmail'])->middleware('signed')->name('api.verification.verify');

        Route::prefix('invitations')->group(function () {
            Route::get('/', [AuthInvitationController::class, 'index']);
            Route::post('{invitation}/accept', [AuthInvitationController::class, 'accept']);
            Route::post('{invitation}/decline', [AuthInvitationController::class, 'decline']);
        });

        Route::prefix('tfa')->group(function () {
            Route::post('challenge', [TfaController::class, 'tfaChallenge']);
            Route::post('enable', [TfaController::class, 'tfaEnable']);
            Route::post('confirm', [TfaController::class, 'tfaConfirm']);
            Route::get('recovery-codes', [TfaController::class, 'tfaRecoveryCodes']);
            Route::post('recovery-codes/regenerate', [TfaController::class, 'tfaRegenerateRecoveryCodes']);
            Route::post('disable', [TfaController::class, 'tfaDisable']);
        });
    });
});

Route::middleware(['auth:web,central-api'])->prefix('tenants')->group(function () {
    Route::get('/', [TenantController::class, 'index']);
    Route::post('/', [TenantController::class, 'store']);
    Route::get('{tenant:slug}', [TenantController::class, 'show']);
    Route::patch('{tenant:slug}', [TenantController::class, 'update']);
    Route::delete('{tenant:slug}', [TenantController::class, 'destroy']);
});

Route::middleware(['auth:web,central-api'])
    ->get('profile-pictures/{profilePicture}', [ProfilePictureController::class, 'show'])
    ->name('central.profile-picture.show');
