<?php

namespace Exdeliver\Elastic\Models;

use Exdeliver\Elastic\Connectors\ElasticConnector;
use Illuminate\Support\Str;

class Searchable extends ElasticConnector
{
    public function indexSearchable()
    {
        $data = $this->toSearchableArray();
        $params = [
            'index' => $this->getSearchableIndex(),
            'id' => $this->getKey(),
            'body' => $data,
        ];

        $this->client->index($params);
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

        $this->client->update($params);
    }

    public function deleteSearchable()
    {
        $params = [
            'index' => $this->getSearchableIndex(),
            'id' => $this->getKey(),
        ];

        $this->client->delete($params);
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
