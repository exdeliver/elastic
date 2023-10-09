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
        $geoLocationType = $this->request->geolocation ?? [];
        $filters = $this->request->filter ?? [];
        $mapping = $this->request->mapping ?? [];

        $elasticQuery = Elastic::make($index);

        if (!empty($geoLocationType)) {
            $elasticQuery = $elasticQuery->whereGeoLocation($geoLocationType);
        }

        foreach ($filters as $column => $data) {
            $data = collect($data);

            if ($data->isEmpty()) {
                continue;
            }

            try {
                $column = !empty($mapping) ? $mapping[$column] : $column;
                if (empty($column)) {
                    continue;
                }
            } catch (Exception $exception) {
                continue;
            }

            if ($data->count() === 1) {
                if ($data->where('*.type.range')->isNotEmpty()) {
                    $range = $data->firstWhere('*.type.range');
                    $elasticQuery = $elasticQuery->whereRange($column, $range['gte'], $range['lt'], 'should');
                } else {
                    $elasticQuery = $elasticQuery->where($column, $data->keys()->first());
                }
            } else {
                if ($data->where('*.type.range')->isNotEmpty()) {
                    $elasticQuery = $elasticQuery->whereIn($column, $data->where('*.type.range')->all(), 'should');
                } else {
                    $elasticQuery = $elasticQuery->whereIn($column, $data->keys()->all());
                }
            }
        }

        if (!empty($search)) {
            $searchColumns = collect(explode(',', $search['columns']))
                ->map(static fn ($column) => $mapping[$column])->toArray();

            $elasticQuery = $elasticQuery->whereSearch($search['term'], $searchColumns);
        }

        $data = $elasticQuery->get(['*'], $size, $page);

        $paginatedResults = ElasticResource::paginate(
            $this->resourceClass,
            $data['data'],
            $data['total'],
            $data['page'],
            $data['size']
        );

        return array_merge([
            'query' => $data['query'],
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
