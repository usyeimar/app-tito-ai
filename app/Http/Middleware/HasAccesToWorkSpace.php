<?php

namespace App\Http\Middleware;

use App\Models\Central\Auth\Authentication\CentralUser;
use App\Services\Central\Tenancy\TenantService;
use Closure;
use Illuminate\Http\Request;

class HasAccesToWorkSpace
{
    public function handle(Request $request, Closure $next)
    {
        if (auth('tenant-api')->check()) {
            return $next($request);
        }

        $centralUser = auth('web')->user();

        if (! $centralUser instanceof CentralUser) {
            abort(401, 'Unauthenticated.');
        }

        $tenants = app(TenantService::class)->listForUser($centralUser);

        $currentTenantSlug = tenant()?->slug;
        $hasAccessToTenant = collect($tenants->toArray()['data'])
            ->pluck('slug')
            ->contains($currentTenantSlug);

        if (! $hasAccessToTenant) {
            abort(403, 'Your do not have access to this workspace. Please contact your workspace administrator.');
        }

        return $next($request);
    }
}
