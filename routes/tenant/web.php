<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\API\Agent\AgentController;
use App\Http\Controllers\Tenant\API\Agent\AgentTestCallController;
use App\Http\Controllers\Tenant\API\KnowledgeBase\KnowledgeBaseController;
use App\Http\Controllers\Tenant\API\KnowledgeBase\KnowledgeBaseDocumentController;
use App\Http\Controllers\Tenant\Web\Agent\AgentPageController;
use App\Http\Controllers\Tenant\Web\KnowledgeBase\KnowledgeBasePageController;
use App\Http\Middleware\HydrateTenantAuth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Passport\Http\Middleware\CreateFreshApiToken;
use Stancl\Tenancy\Features\UserImpersonation;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

/*
|--------------------------------------------------------------------------
| Tenant Web (Inertia) Routes
|--------------------------------------------------------------------------
|
| These routes are mounted under `/{tenant}/...` via the path identification
| middleware and serve Inertia React pages for the workspace area.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByPath::class,
    HydrateTenantAuth::class,
])->prefix('{tenant}')->group(function (): void {
    Route::get('/impersonate/{token}', function (string $token) {
        return UserImpersonation::makeResponse($token);
    })->name('tenant.impersonate');
});

Route::middleware([
    'web',
    InitializeTenancyByPath::class,
    HydrateTenantAuth::class,
    'auth:tenant',
    'has-access-to-workspace',
    CreateFreshApiToken::using('tenant'),
])->prefix('{tenant}')->group(function (): void {
    Route::get('/dashboard', function () {
        return Inertia::render('dashboard');
    })->name('tenant.dashboard');

    Route::prefix('agents')->name('tenant.agents.')->group(function (): void {
        Route::get('/', [AgentPageController::class, 'index'])->name('index');
        Route::get('{agent}', [AgentPageController::class, 'show'])->name('show');
        Route::post('/', [AgentPageController::class, 'store'])->name('store');
        Route::patch('{agent}', [AgentController::class, 'update'])->name('update');
        Route::delete('{agent}', [AgentController::class, 'destroy'])->name('destroy');
        Route::post('{agent}/test-call', [AgentTestCallController::class, 'start'])->name('test-call.start');
        Route::delete('{agent}/test-call/{session}', [AgentTestCallController::class, 'stop'])->name('test-call.stop');
    });

    Route::prefix('runner/sessions')->name('tenant.runner.sessions.')->group(function (): void {
        Route::get('{sessionId}/status', [AgentTestCallController::class, 'status'])->name('status');
        Route::post('{sessionId}/user-ended', [AgentTestCallController::class, 'userEnded'])->name('user-ended');
    });

    Route::prefix('knowledge')->name('tenant.knowledge.')->group(function (): void {
        Route::get('/', [KnowledgeBasePageController::class, 'index'])->name('index');
    });

    Route::prefix('knowledge-bases')->name('tenant.knowledge-bases.')->group(function (): void {
        Route::get('/', [KnowledgeBaseController::class, 'index'])->name('index');
        Route::post('/', [KnowledgeBaseController::class, 'store'])->name('store');
        Route::delete('{knowledgeBase}', [KnowledgeBaseController::class, 'destroy'])->name('destroy');

        Route::prefix('{knowledgeBase}/documents')->name('documents.')->group(function (): void {
            Route::get('/', [KnowledgeBaseDocumentController::class, 'index'])->name('index');
            Route::post('/', [KnowledgeBaseDocumentController::class, 'store'])->name('store');
            Route::delete('{document}', [KnowledgeBaseDocumentController::class, 'destroy'])->name('destroy');
        });
    });
});
