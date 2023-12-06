<?php

namespace Exdeliver\Elastic\Models;

use Elastic\Elasticsearch\Client;
use Exdeliver\Elastic\Connectors\ElasticConnector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Searchable extends Model
{
    public ?Client $elastic = null;

    public function elastic(): Client
    {
        return ElasticConnector::make()->getClient();
    }

    public function indexSearchable()
    {
        $data = $this->toSearchableArray();
        $params = [
            'index' => $this->getSearchableIndex(),
            'id' => $this->getKey(),
            'body' => $data,
        ];

        $this->elastic()->index($params);
    }

    public function updateSearchable()
    {
        $data = $this->toSearchableArray();
        $params = [
            'index' => $this->getSearchableIndex(),
            'id' => $this->getKey(),
            'body' => [
                'doc' => $data,
            ],
        ];

        $this->elastic()->update($params);
    }

    public function deleteSearchable()
    {
        $params = [
            'index' => $this->getSearchableIndex(),
            'id' => $this->getKey(),
        ];

        $this->elastic()->delete($params);
    }

    protected function toSearchableArray(): array
    {
        return $this->toArray();
    }

    protected function getSearchableIndex(): string
    {
        return Str::snake(class_basename($this));
    }
}
