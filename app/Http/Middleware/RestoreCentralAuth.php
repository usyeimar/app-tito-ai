<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\Auth\Authentication\CentralUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restores central session auth from an encrypted cookie.
 *
 * When a user enters a tenant workspace via impersonation, the session ID
 * is regenerated and saved to the tenant database. The central database
 * loses track of the session, so navigating back to central routes
 * (e.g. /workspaces) would appear unauthenticated.
 *
 * This middleware checks for a `central_auth_user` cookie (set during login)
 * and re-authenticates the user on central routes when the session is empty.
 * It also ensures the cookie stays fresh for already-authenticated users.
 */
class RestoreCentralAuth
{
    public const COOKIE_NAME = 'central_auth_user';

    /** Cookie lifetime in minutes (30 days). */
    public const COOKIE_LIFETIME = 43200;

    public function handle(Request $request, Closure $next): Response
    {
        // Already authenticated — ensure cookie is set for future cross-context visits.
        if (auth('web')->check()) {
            $user = auth('web')->user();

            if ($user instanceof CentralUser && ! $request->cookie(self::COOKIE_NAME)) {
                Cookie::queue(self::cookie($user));
            }

            return $next($request);
        }

        // Not authenticated — try to restore from cookie.
        $userId = $request->cookie(self::COOKIE_NAME);

        if (! is_string($userId) || $userId === '') {
            return $next($request);
        }

        $user = CentralUser::find($userId);

        if ($user) {
            auth('web')->login($user);
        } else {
            Cookie::queue(Cookie::forget(self::COOKIE_NAME));
        }

        return $next($request);
    }

    /**
     * Create the central auth cookie for the given user.
     */
    public static function cookie(CentralUser $user): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            self::COOKIE_NAME,
            (string) $user->getKey(),
            self::COOKIE_LIFETIME,
            '/',
            null,
            null,
            true,
        );
    }
}
