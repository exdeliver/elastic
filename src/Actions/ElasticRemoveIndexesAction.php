<?php

namespace Exdeliver\Elastic\Actions;

use Exdeliver\Elastic\Connectors\ElasticConnector;
use Illuminate\Http\Request;

final class ElasticRemoveIndexesAction extends ElasticConnector
{
    public function handle(?Request $request = null): array
    {
        $data = [];

        /** @var \Exdeliver\Elastic\Resources\ElasticResourceContract $index */
        foreach (ElasticCreateIndexesAction::indexes() as $index) {
            $indexName = self::environment() . config('elastic.prefix') . '_' . $index::elastic()['index'];

            if ($this->client->indices()->exists([
                    'index' => $indexName,
                ])->getStatusCode() === 404) {
                continue;
            }

            $data[] = $indexName;

            $this->client->deleteByQuery([
                'index' => $indexName,
                'conflicts' => 'proceed',
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass(),
                    ],
                ],
            ]);

            $this->client->indices()->delete([
                'index' => $indexName,
            ]);
        }

        $data = collect($data);

        return [
            'total' => $data->count(),
            'data' => $data->toJson(),
        ];
    }
}
