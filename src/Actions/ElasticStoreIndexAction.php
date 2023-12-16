<?php

namespace Exdeliver\Elastic\Actions;

use Exdeliver\Elastic\Models\CsvModel;
use Exdeliver\Elastic\Connectors\ElasticConnector;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Database\Eloquent\Model;
use Mockery\Exception;

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
            if ($resource::model() instanceof Model) {
                $model = $resource::model()->query();
                $this->processData($model, $resource);
            }

            if ($resource::model() instanceof CsvModel) {
                $indexName = self::environment() . config('elastic.prefix') . '_' . $resource::elastic()['index'];

                try {
                    $model = $resource::model()->all();
                    foreach ($model as $row) {
                        $row = $row->toArray();

                        $this->insert($indexName, ['body' => $row]);
                    }
                } catch (Exception $e) {
                    throw new \Exception($e);
                }
            }
        }

        $data = collect($data);

        return [
            'total' => $data->count(),
            'data' => $data->toJson(),
        ];
    }

    private function processData($model, $resource)
    {
        $model->chunk(50, function ($chunkedCollection) use ($resource) {
            $chunkedCollection->each(function ($row) use ($resource) {
                $resource = $resource::make($row)->toElastic(request());

                $indexName = self::environment() . config('elastic.prefix') . '_' . $resource['index'];

                $this->insert($indexName, $resource);
            });
        });
    }

    private function insert(string $indexName, $resource)
    {
        if (!$this->client->indices()->exists([
                'index' => $indexName,
            ])->getStatusCode() === 404) {
            throw new NotFoundException(sprintf('Index %s does not exists', $indexName));
        }

        $resource['index'] = $indexName;

        $this->client->index($resource);
    }
}
