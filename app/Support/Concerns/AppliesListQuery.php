<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesListQuery
{
    /**
     * Apply pagination, sort, direction, and search from the request.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $sortable
     * @param  list<string>  $searchable
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyListQuery(
        Builder $query,
        Request $request,
        array $sortable,
        array $searchable = [],
        string $defaultSort = 'id',
        string $defaultDirection = 'desc',
    ): Builder {
        $search = $request->string('search')->trim()->toString();

        if ($search !== '' && $searchable !== []) {
            $query->where(function (Builder $builder) use ($search, $searchable): void {
                foreach ($searchable as $index => $column) {
                    if ($index === 0) {
                        $builder->where($column, 'like', "%{$search}%");
                    } else {
                        $builder->orWhere($column, 'like', "%{$search}%");
                    }
                }
            });
        }

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, $sortable, true) ? $sort : $defaultSort;

        $requestedDirection = strtolower($request->string('direction')->toString());
        $direction = match ($requestedDirection) {
            'asc' => 'asc',
            'desc' => 'desc',
            default => $defaultDirection === 'asc' ? 'asc' : 'desc',
        };

        return $query->orderBy($sort, $direction);
    }

    protected function perPage(Request $request, int $default = 25): int
    {
        $perPage = $request->integer('per_page', $default);

        return max(1, min(100, $perPage > 0 ? $perPage : $default));
    }
}
