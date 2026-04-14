<?php

declare(strict_types=1);

use App\Http\Controllers\Shared\ProfilePictureController;
use App\Http\Controllers\Tenant\API\Activity\ActivityController;
use App\Http\Controllers\Tenant\API\Auth\Authentication\TenantAuthController;
use App\Http\Controllers\Tenant\API\Auth\Impersonation\TenantImpersonationController;
use App\Http\Controllers\Tenant\API\Auth\Invitation\InvitationController as TenantInvitationController;
use App\Http\Controllers\Tenant\API\Auth\Role\PermissionController;
use App\Http\Controllers\Tenant\API\Auth\Role\RoleController;
use App\Http\Controllers\Tenant\API\Auth\Token\TokenController as TenantTokenController;
use App\Http\Controllers\Tenant\API\Auth\User\UserController;
use App\Http\Controllers\Tenant\API\Commons\EntityFaviconController;
use App\Http\Controllers\Tenant\API\Commons\EntityProfilePictureController;
use App\Http\Controllers\Tenant\API\Notifications\NotificationsController;
use App\Http\Middleware\AuditTenantRequest;
use App\Http\Middleware\CaptureActivityContext;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
|
| Tenant routes are registered under the `tenant` route mode middleware
| (see TenancyServiceProvider). When using path identification, tenant
| routes need the `{tenant}` path parameter.
|
*/

Route::middleware([
    InitializeTenancyByPath::class,
    'api',
])->prefix('{tenant}')->group(function () {
    Route::post('/api/impersonate/{token}', [TenantImpersonationController::class, 'start']);
    Route::post('/api/refresh', [TenantTokenController::class, 'refresh'])->middleware('throttle:auth.refresh');
    Route::post('/api/impersonate/end', [TenantImpersonationController::class, 'end'])
        ->middleware('auth:tenant-api');

    Route::match(['get', 'post'], '/api/broadcasting/auth', [BroadcastController::class, 'authenticate'])
        ->middleware('auth:tenant-api');

    require __DIR__.'/api/ai/public-agents-config.php';

});

Route::middleware([
    InitializeTenancyByPath::class,
    'api',
    AuditTenantRequest::class,
    'auth:tenant-api',
    CaptureActivityContext::class,
])->prefix('{tenant}/api')
    ->group(function () {
        Route::get('profile-pictures/{profilePicture}', [ProfilePictureController::class, 'show'])
            ->name('tenant.profile-picture.show');
        Route::get('entity-profile-pictures/{entityProfilePicture}', [EntityProfilePictureController::class, 'show'])
            ->name('tenant.entity-profile-picture.show');
        Route::get('entity-favicons/{entityFavicon}', [EntityFaviconController::class, 'show'])
            ->name('tenant.entity-favicon.show');

        Route::get('auth/me', [TenantAuthController::class, 'me']);
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationsController::class, 'index']);
            Route::post('batch/read', [NotificationsController::class, 'batchMarkRead']);
            Route::post('batch/delete', [NotificationsController::class, 'batchDestroy']);
            Route::post('{notification}/read', [NotificationsController::class, 'markRead']);
            Route::delete('{notification}', [NotificationsController::class, 'destroy']);
        });

        Route::get('permissions', [PermissionController::class, 'index']);
        Route::get('activity', [ActivityController::class, 'index']);

        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleController::class, 'index']);
            Route::post('/', [RoleController::class, 'store']);
            Route::get('{role}', [RoleController::class, 'show']);
            Route::match(['put', 'patch'], '{role}', [RoleController::class, 'update']);
            Route::delete('{role}', [RoleController::class, 'destroy']);
        });

        // System
        Route::prefix('system')->group(function () {
            require __DIR__.'/api/system/column-configurations.php';
            require __DIR__.'/api/system/user-column-configurations.php';
        });

        // Agents
        Route::prefix('ai')->group(function () {
            require __DIR__.'/api/ai/agents.php';
            require __DIR__.'/api/ai/trunks.php';
            // Knowledge Base
            require __DIR__.'/api/ai/knowledge-base.php';
        });

        // Users
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('{user}', [UserController::class, 'show']);
            Route::patch('{user}', [UserController::class, 'update']);
            Route::patch('{user}/password', [UserController::class, 'updatePassword'])
                ->middleware('throttle:tenant.users.reset_password');
            Route::delete('{user}', [UserController::class, 'destroy']);
            Route::post('{user}/roles', [UserController::class, 'assignRoles']);
            Route::delete('{user}/roles/{role}', [UserController::class, 'revokeRole']);
        });

        // Invitations
        Route::prefix('invitations')->group(function () {
            Route::get('/', [TenantInvitationController::class, 'index']);
            Route::post('/', [TenantInvitationController::class, 'store']);
            Route::post('/batch', [TenantInvitationController::class, 'storeBatch']);
            Route::post('{invitation}/reinvite', [TenantInvitationController::class, 'reinvite']);
            Route::post('{invitation}/resend', [TenantInvitationController::class, 'resend'])
                ->middleware('throttle:tenant.invitations.resend');
            Route::delete('{invitation}', [TenantInvitationController::class, 'revoke']);
        });

    });
