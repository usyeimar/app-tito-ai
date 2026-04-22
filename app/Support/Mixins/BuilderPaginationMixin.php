<?php

declare(strict_types=1);

namespace App\Support\Mixins;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query/Eloquent Builder mixin exposing ->paginateFromFilters([...]).
 *
 * Reads canonical pagination keys (page.size / page.number) with sane defaults
 * so every list endpoint produces a uniform LengthAwarePaginator.
 */
class BuilderPaginationMixin
{
    public function paginateFromFilters(): Closure
    {
        return function (array $filters = [], int $defaultPerPage = 20, int $maxPerPage = 100) {
            /** @var Builder|\Illuminate\Database\Query\Builder $this */
            $perPage = (int) data_get($filters, 'page.size', $defaultPerPage);
            $page = (int) data_get($filters, 'page.number', 1);

            $perPage = max(1, min($perPage, $maxPerPage));
            $page = max(1, $page);

            return $this->paginate(perPage: $perPage, page: $page);
        };
    }
}
