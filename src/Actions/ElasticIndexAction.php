<?php

namespace Exdeliver\Elastic\Actions;

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
        $elasticQuery = Elastic::make($index);

        $filters = $this->request->get('filter', null);
        $mapping = $this->request->get('mapping', null);

        if (!empty($filters)) {
            try {
                $filters = !is_array($filters) && Str::isJson($filters)
                    ? json_decode($filters, true, 512, JSON_THROW_ON_ERROR)
                    : $filters;
            } catch (\JsonException $e) {
                $filters = [];
            }

            foreach ($filters as $column => $data) {
                if (empty($data)) {
                    continue;
                }

                $column = !empty($mapping) ? $mapping[$column] : $column;

                if (count(array_keys($data)) === 1) {
                    $elasticQuery = $elasticQuery->where($column, array_keys($data)[0]);
                } else {
                    $elasticQuery = $elasticQuery->whereIn($column, array_keys($data));
                }
            }
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
}
