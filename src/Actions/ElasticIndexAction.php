<?php

namespace Exdeliver\Elastic\Actions;

use Exception;
use Exdeliver\Elastic\Connectors\ElasticConnector;
use Exdeliver\Elastic\Resources\ElasticResource;
use Exdeliver\Elastic\Services\Elastic;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ElasticIndexAction extends ElasticConnector
{
    protected ?Request $request = null;

    protected string $resourceClass;

    public function handle(Request $request, string $resourceClass, string $index, ?string $uuid = null): array
    {
        $this->request = $request;
        $this->resourceClass = $resourceClass;

        if (!$this->indexExists($index)) {
            throw new NotFoundException(sprintf('Index %s not found', $index));
        }

        if ($uuid !== null) {
            return $this->getResource($index, $uuid);
        }

        return $this->getAll($index, 50, $request->integer('page', 1));
    }

    private function indexExists(string $index): bool
    {
        return $this->client
            ->indices()
            ->exists([
                'index' => $index,
            ])
            ->getStatusCode() === 200;
    }

    private function getAll(string $index, int $size = 10, int $page = 1): array
    {
        $search = $this->request->search ?? null;
        $filters = $this->request->filter ?? [];
        $orderBy = $this->request->sort ?? null;
        $orderDirection = $this->request->direction ?? 'asc';
        $mapping = $this->request->mapping ?? [];

        $elasticQuery = Elastic::make($index);

        foreach ($filters as $column => $query) {
            if (empty($query)) {
                continue;
            }

            $value = $query['value'] ?? null;

            $column = !empty($mapping) ? $mapping[$column] : $column;

            if (empty($value) || empty($column)) {
                continue;
            }

            $condition = $query['condition'] ?? '=';

            $type = $query['type'] ?? 'missing query type';
            $strict = $query['strict'] ?? 'should';

            match ($type) {
                'whereRange' => $elasticQuery->whereRange(
                    $column,
                    $value,
                    $condition['lt'],
                    $condition['gte'],
                    'should'
                ),
                'whereIn' => $elasticQuery->whereIn($column, $value, $condition),
                'whereDate' => $elasticQuery->whereDate($column, $value, $condition, $strict),
                'whereDateBetween' => $elasticQuery->whereDateBetween($column, $value['gte'], $value['lt']),
                'where' => $elasticQuery->where($column, $value, $condition),
                'whereGeoDistance' => $elasticQuery->whereGeoDistance($column, $value),
                default => throw new Exception(sprintf('You are missing type %s in query', $type)),
            };
        }

        if (!empty($search)) {
            $searchColumns = collect(explode(',', $search['columns']))
                ->map(static fn ($column) => $mapping[$column])->toArray();
            $elasticQuery = $elasticQuery->whereSearch($search['term'], $searchColumns);
        }

        if (!empty($orderBy)) {
            $elasticQuery = $elasticQuery->orderBy($orderBy, $orderDirection);
        }

        $data = $elasticQuery->get(['*'], $size, $page);

        $paginatedResults = ElasticResource::paginate(
            $this->resourceClass,
            $data['data'],
            $data['total'],
            $data['page'],
            $data['size'],
            $orderBy,
            $orderDirection,
        );

        return array_merge([
            'query' => $data['query'],
            'order' => [
                'columns' => $orderBy,
                'direction' => $orderDirection,
            ],
        ], (new ElasticResource($paginatedResults))->toArray($this->request));
    }

    private function getResource(string $index, string $uuid): array
    {
        $data = Elastic::make($index)->where('uuid', $uuid)->first();

        return ElasticResource::make($data)->toArray($this->request);
    }

    private function prepareForValidation(): array
    {
        $filters = $this->request->filter ?? [];
        if (is_string($filters) && Str::isJson($filters)) {
            try {
                $filters = json_decode($filters, true, 512, JSON_THROW_ON_ERROR);
                $this->request->replace([
                    'filter' => $filters,
                ]);
            } catch (\JsonException $e) {
            }
        }

        return $this->request->all();
    }
}
