<?php

namespace Exdeliver\Elastic\Actions;

use App\Http\Resources\AdvertisementResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\FeatureResource;
use Exdeliver\Elastic\Connectors\ElasticConnector;
use Illuminate\Http\Request;

final class ElasticCreateIndexesAction extends ElasticConnector
{
    public function handle(?Request $request = null): array
    {
        $data = [];

        /** @var \Exdeliver\Elastic\Resources\ElasticResourceContract $index */
        foreach (self::indexes() as $index) {
            $indexName = $index::elastic()['index'];
            if ($this->client
                ->indices()
                ->exists([
                    'index' => $indexName,
                ])->getStatusCode() === 200) {
                continue;
            }
            $data[] = $indexName;

            $created = $this->client->indices()->create(
                $index::elastic() + [
                    'client' => [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ],
                    ],
                ])['index'];
        }

        $data = collect($data);

        return [
            'index' => $created ?? null,
            'total' => $data->count(),
            'data' => $data->toJson(),
        ];
    }

    public static function indexes(): array
    {
        return [
            CountryResource::class,
            AdvertisementResource::class,
            EventResource::class,
            FeatureResource::class,
        ];
    }
}
