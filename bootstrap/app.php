<?php

use App\Exceptions\Renderers\CoreExceptionRenderer;
use App\Exceptions\Renderers\Tenant\AuthExceptionRenderer;
use App\Exceptions\Renderers\Tenant\SystemExceptionRenderer;
use App\Http\Middleware\EnsureCookieAuthOrigin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HasAccesToWorkSpace;
use App\Http\Middleware\HydrateCentralAuth;
use App\Http\Middleware\HydrateTenantAuth;
use App\Http\Middleware\InjectAccessTokenFromCookie;
use App\Http\Middleware\ShareWorkspacesWithInertia;
use App\Http\Middleware\WrapApiResponses;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/central/web.php',
        api: __DIR__.'/../routes/central/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/health',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Ensure HydrateCentralAuth and HydrateTenantAuth run after
        // StartSession but before Authenticate in the middleware priority.
        // Without this, Laravel's priority system reorders Authenticate:tenant
        // to run before these middleware, causing auth failures on tenant routes.
        $middleware->appendToPriorityList(ShareErrorsFromSession::class, HydrateCentralAuth::class);
        $middleware->appendToPriorityList(HydrateCentralAuth::class, HydrateTenantAuth::class);

        $middleware->web(append: [
            HydrateCentralAuth::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            InjectAccessTokenFromCookie::class,
            ShareWorkspacesWithInertia::class,
        ]);

        $middleware->prependToGroup('api', [
            // StartSession::class,
            InjectAccessTokenFromCookie::class,
            EnsureCookieAuthOrigin::class,
        ]);

        $middleware->appendToGroup('api', WrapApiResponses::class);

        $middleware->alias([
            'has-access-to-workspace' => HasAccesToWorkSpace::class,
        ]);

        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $_exception): bool {
            return $request->expectsJson() || $request->is('api/*') || $request->is('*/api/*') || $request->is('oauth/*');
        });

        AuthExceptionRenderer::register($exceptions);
        SystemExceptionRenderer::register($exceptions);
        CoreExceptionRenderer::register($exceptions);

    })->create();
