<?php

namespace Exdeliver\Elastic\Actions;

use Exdeliver\Elastic\Connectors\ElasticConnector;
use Illuminate\Database\Eloquent\Model;

final class ElasticDestroyIndexAction extends ElasticConnector
{
    public function handle(array $resources, Model $model, ?string $uuid = null): array
    {
        $this->client->delete($index);

        $data = collect([
            'uuid' => $uuid,
        ]);

        return [
            'total' => 1,
            'data' => $data->toJson(),
        ];
    }
}
