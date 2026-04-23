<?php

namespace App\Exceptions\Renderers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiErrorRenderer
{
    protected static function shouldRenderJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->is('api/*')
            || $request->is('*/api/*')
            || $request->is('oauth/*');
    }

    protected static function singleErrorResponse(
        int $status,
        string $code,
        string $title,
        string $detail,
        array $extra = [],
    ): JsonResponse {
        return response()->json([
            'errors' => [
                array_merge([
                    'status' => $status,
                    'code' => $code,
                    'title' => $title,
                    'detail' => $detail,
                ], $extra),
            ],
        ], $status);
    }
}
