<?php

namespace Exdeliver\Elastic\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ElasticResource extends JsonResource
{
    public static function paginate(
        string $resourceClass,
        array $resource,
        int $total,
        int $page = 1,
        ?int $perPage = 10,
        ?string $order = 'uuid',
        ?string $direction = 'asc',
    ): LengthAwarePaginator {
        $collection = collect($resource)
            ->map(static fn($hit) => new $resourceClass($hit))
            ->sortBy(fn($hit) => strtotime($hit['_source'][$order]));

        $currentPage = $page;

        $paginator = new LengthAwarePaginator($collection, $total, $perPage, $currentPage);

        return $paginator->withPath(request()->path());
    }

    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
