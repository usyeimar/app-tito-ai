<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\API\Agent\AgentController;
use App\Http\Controllers\Tenant\API\Agent\AgentDeploymentController;
use App\Http\Controllers\Tenant\API\Agent\AgentSessionAudioController;
use App\Http\Controllers\Tenant\API\Agent\AgentSessionWebhookController;
use App\Http\Controllers\Tenant\API\Agent\AgentTestCallController;
use App\Http\Controllers\Tenant\API\Agent\AgentToolController;
use App\Http\Controllers\Tenant\API\Conversations\ConversationController;
use App\Http\Middleware\VerifyRunnerSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('agents')->name('agents.')
    ->group(function () {
        Route::get('/', [AgentController::class, 'index']);
        Route::post('/', [AgentController::class, 'store']);
        Route::get('summaries', [AgentController::class, 'summaries'])->name('summaries');
        Route::get('{agent}', [AgentController::class, 'show']);
        Route::patch('{agent}', [AgentController::class, 'update']);
        Route::delete('{agent}', [AgentController::class, 'destroy']);
        Route::post('{agent}/duplicate', [AgentController::class, 'duplicate'])->name('duplicate');

        // Deployments (nested resource)
        Route::apiResource('{agent}/deployments', AgentDeploymentController::class)
            ->parameters(['deployments' => 'deployment']);

        // Tools (nested resource)
        Route::apiResource('{agent}/tools', AgentToolController::class)
            ->parameters(['tools' => 'tool']);

        Route::post('{agent}/test-call', [AgentTestCallController::class, 'start'])
            ->name('test-call.start');
        Route::delete('{agent}/test-call/{session}', [AgentTestCallController::class, 'stop'])
            ->name('test-call.stop');
    });

// Conversations (workspace-level, not nested under agent)
Route::prefix('conversations')->name('conversations.')->group(function () {
    Route::get('/', [ConversationController::class, 'index']);
    Route::get('{conversation}', [ConversationController::class, 'show']);
    Route::get('{conversation}/transcripts', [ConversationController::class, 'transcripts'])->name('transcripts');
    Route::delete('{conversation}', [ConversationController::class, 'destroy']);
});

// Session status endpoints (no auth required for polling)
Route::get('runner/sessions/{sessionId}/status', [AgentTestCallController::class, 'status'])
    ->name('ai.runner.session.status')
    ->withoutMiddleware(['auth:tenant-api', 'auth:api']);

Route::post('runner/sessions/{sessionId}/user-ended', [AgentTestCallController::class, 'userEnded'])
    ->name('ai.runner.session.user-ended')
    ->withoutMiddleware(['auth:tenant-api', 'auth:api']);

Route::get('runner/sessions/{sessionId}/transcripts', [AgentTestCallController::class, 'transcripts'])
    ->name('ai.runner.session.transcripts')
    ->withoutMiddleware(['auth:tenant-api', 'auth:api']);

// Runner endpoints: HMAC signature verification + rate limiting
Route::middleware([VerifyRunnerSignature::class, 'throttle:runner-webhook'])
    ->withoutMiddleware(['auth:tenant-api', 'auth:api'])
    ->group(function () {
        Route::post('runner/webhook', [AgentSessionWebhookController::class, 'handle'])
            ->name('ai.runner.webhook');

        Route::post('runner/sessions/{sessionId}/audio', [AgentSessionAudioController::class, 'store'])
            ->name('ai.runner.session.audio');
    });
