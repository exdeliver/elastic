<?php

namespace Exdeliver\Elastic\Actions;

use Exdeliver\Elastic\Connectors\ElasticConnector;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Database\Eloquent\Model;

final class ElasticStoreIndexAction extends ElasticConnector
{
    public function handle(string|array $resources, ?Model $model = null, ?string $uuid = null): array
    {
        $data = [];

        if (!is_array($resources)) {
            /** @var \Illuminate\Http\Resources\Json\JsonResource $resource */
            $resource = new $resources();
            $this->client->index($resource::make($model)->toElastic(request()));

            return [];
        }

        /** @var \Illuminate\Http\Resources\Json\JsonResource $resource */
        foreach ($resources as $resource) {
            /** @var \Illuminate\Database\Eloquent\Builder $model */
            $model = $resource::model()->query();
            $model->chunk(50, function ($chunkedCollection) use ($resource, &$data) {
                $chunkedCollection->each(function ($row) use ($resource, &$data) {
                    $resource = $resource::make($row)->toElastic(request());

                    if (!$this->client->indices()->exists([
                        'index' => $resource['index'],
                    ])->getStatusCode() === 404) {
                        throw new NotFoundException(sprintf('Index %s does not exists', $resource['index']));
                    }

                    $data[] = [
                        'index' => $resource['index'],
                        'uuid' => $resource['body']['uuid'] ?? null,
                    ];

                    $this->client->index($resource);
                });
            });
        }

        $data = collect($data);

        return [
            'total' => $data->count(),
            'data' => $data->toJson(),
        ];
    }
}
