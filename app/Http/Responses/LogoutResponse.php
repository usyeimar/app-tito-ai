<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Middleware\RestoreCentralAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): Response
    {
        Cookie::queue(Cookie::forget(RestoreCentralAuth::COOKIE_NAME));

        return $request instanceof Request && $request->expectsJson()
            ? response()->json(['message' => 'Logged out.'])
            : redirect('/');
    }
}
