<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait PaginatesJsonResponses
{
    /**
     * Build a standard paginated JSON envelope.
     *
     * @param  LengthAwarePaginator<mixed>  $paginator
     * @param  callable(mixed, int): mixed|null  $mapper
     * @param  array<string, mixed>  $extra
     */
    protected function paginatedJson(
        LengthAwarePaginator $paginator,
        ?callable $mapper = null,
        array $extra = [],
        int $status = 200,
    ): JsonResponse {
        $items = $mapper
            ? collect($paginator->items())->values()->map($mapper)->values()
            : collect($paginator->items())->values();

        $payload = [
            'data' => $items,
            '_links' => [
                'self' => ['href' => $paginator->url($paginator->currentPage()), 'method' => 'GET'],
                'first' => ['href' => $paginator->url(1), 'method' => 'GET'],
                'last' => ['href' => $paginator->url($paginator->lastPage()), 'method' => 'GET'],
                'next' => $paginator->nextPageUrl() ? ['href' => $paginator->nextPageUrl(), 'method' => 'GET'] : null,
                'prev' => $paginator->previousPageUrl() ? ['href' => $paginator->previousPageUrl(), 'method' => 'GET'] : null,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return response()->json(array_merge($payload, $extra), $status);
    }
}
