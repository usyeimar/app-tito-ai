<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\Auth\Authentication\CentralUser;
use App\Models\Tenant\Auth\Authentication\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restores the tenant 'tenant' guard from the authenticated central user.
 *
 * With database sessions and path-based tenancy, StartSession loads session
 * data from the central database before tenancy is initialized. After
 * InitializeTenancyByPath switches the DB connection, the tenant guard has
 * no user because the session data came from the central DB.
 *
 * This middleware resolves the tenant user by matching the central user's
 * global_id and authenticates them on the 'tenant' guard.
 *
 * Must run after InitializeTenancyByPath and HydrateCentralAuth,
 * but before Authenticate:tenant. Priority is set in bootstrap/app.php.
 */
class HydrateTenantAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenant() || auth('tenant')->check()) {
            return $next($request);
        }

        $centralUser = auth('web')->user();

        if (! $centralUser instanceof CentralUser) {
            return $next($request);
        }

        $tenantUser = User::query()
            ->where('global_id', $centralUser->global_id)
            ->where('is_active', true)
            ->first();

        if ($tenantUser) {
            auth('tenant')->login($tenantUser);

            if (app()->environment('local')) {
                Log::debug('HydrateTenantAuth: restored from central user', [
                    'path' => $request->path(),
                    'tenant' => tenant()->getTenantKey(),
                    'tenant_user_id' => $tenantUser->getKey(),
                ]);
            }
        }

        return $next($request);
    }
}
