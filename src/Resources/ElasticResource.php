<?php

namespace Exdeliver\Elastic\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ElasticResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }

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
            ->sortBy($order, $direction)
            ->map(static fn ($hit) => new $resourceClass($hit));

        $currentPage = $page;

        $paginator = new LengthAwarePaginator($collection, $total, $perPage, $currentPage);

        return $paginator->withPath(request()->path());
    }
}
