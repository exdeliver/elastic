<?php

namespace Exdeliver\Elastic\Services;

use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Exdeliver\Elastic\Connectors\ElasticConnector;
use http\Exception\InvalidArgumentException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Elastic extends ElasticConnector
{
    protected string $index;

    protected array $query = [
        'match_all' => [],
    ];

    protected ?array $join = null;

    private bool $isFiltered = false;

    private bool $isRandomized = false;

    public function __construct(string $index, ?ClientBuilder $client = null)
    {
        $this->index = $index;

        parent::__construct();

        $this->query = [
            'query' => [],
        ];
    }

    public static function make(string $index): Elastic
    {
        return new self($index);
    }

    public function whereIn(
        string $field,
        array $values,
        string $operator = '=',
        ?string $strict = 'should',
        ?string $exact = 'match'
    ): self {
        foreach ($values as $value) {
            $type = $value['type'] ?? null;
            if ($type === 'range') {
                $this->whereRange($field, $value['gte'], $value['lt'], $strict);
            } else {
                $this->where($field, $value, $operator, $strict, $exact);
            }
        }

        return $this;
    }

    public function whereRange(string $field, mixed $gte, mixed $lt, string $type = 'filter'): self
    {
        if (!in_array($type, ['should', 'filter'], true)) {
            throw new InvalidArgumentException(sprintf('Invalid argument %s', $type));
        }

        $this->isFiltered = true;

        $this->query['query']['bool'][$type][] = [
            'range' => [
                $field => [
                    'gte' => $gte,
                    'lt' => $lt,
                ],
            ],
        ];

        return $this;
    }

    public function whereSearch(string $text, array $columnsToSearch, array $search): self
    {
        $this->isFiltered = true;

        $params = [
            'query' => $text,
            'fields' => $columnsToSearch,
        ];

        if (!empty($search['type']) && $search['type'] === 'suggest') {
            $params['type'] = 'bool_prefix';
        } else {
            $params['fuzziness'] = 1;
        }

        $this->query['query']['bool']['must'][] = [
            'multi_match' => $params,
        ];

        $sortColumns = collect($columnsToSearch)->map(static fn($column) => [
            $column . '.keyword' => 'asc',
        ])->all();

        return $this;
    }

    public function whereSuggest(string $text, array $columnsToSearch): self
    {
        $this->isFiltered = true;

        $this->query['query']['bool']['must'][] = [
            'multi_match' => [
                'query' => $text,
                'fields' => $columnsToSearch,
                'type' => 'bool_prefix',
            ],
        ];

        return $this;
    }

    public function whereGeoDistance(string $field, array $value): self
    {
        $this->isFiltered = true;

        $this->query['query']['bool']['must'][] = [
            'geo_distance' => [
                'distance' => $value['distance'],
                $field => [
                    'lat' => $value['lat'],
                    'lon' => $value['lon'],
                ],
            ],
        ];

        return $this;
    }

    public function whereDate(string $field, string $date, string $condition = 'gte', string $strict = 'must'): self
    {
        $this->isFiltered = true;

        $this->query['query']['bool'][$strict][] = [
            'range' => [
                $field => [
                    $condition => $date,
                ],
            ],
        ];

        return $this;
    }

    public function where(
        string $field,
        $value,
        string $operator = '=',
        ?string $strict = 'must',
        ?string $exact = 'match'
    ): self {
        if (!in_array($operator, ['=', '>', '<', '>=', '<=', 'LIKE'], true)) {
            throw new InvalidArgumentException('Invalid operator');
        }

        $this->isFiltered = true;

        if ($operator === 'LIKE') {
            $this->query['query']['bool'][$strict][] = [
                'wildcard' => [
                    $field => '*' . $value . '*',
                ],
            ];
        } else {
            $fieldArray = explode('.', $field);
            $isNested = count($fieldArray) > 1;
            if (!$isNested) {
                $this->query['query']['bool'][$strict][] = [
                    $exact => [
                        $field => $value,
                    ],
                ];
            } else {
                $this->query['query']['bool'][$strict][] = [
                    'nested' => [
                        'path' => $fieldArray[0],
                        'query' => [
                            $exact => [
                                $field => $value,
                            ],
                        ],
                    ],
                ];
            }
        }

        return $this;
    }

    public function find($id)
    {
        $params = [
            'index' => $this->index,
            'body' => [
                'query' => $this->isFiltered ? $this->query['query'] : [
                    'match_all' => new \stdClass(),
                ],
            ],
            'id' => $id,
        ];

        $response = $this->client->get($params);

        return $response['_source'];
    }

    public function findOrFail($id)
    {
        $params = [
            'index' => $this->index,
            'body' => [
                'query' => $this->isFiltered ? $this->query['query'] : [
                    'match_all' => new \stdClass(),
                ],
            ],
            'id' => $id,
        ];

        try {
            $response = $this->client->get($params);
        } catch (ElasticsearchException $e) {
            throw new ModelNotFoundException();
        }

        return $response['_source'];
    }

    public function first(): array
    {
        $data = [];
        $params = [
            'index' => $this->index,
            'size' => 1,
            'body' => [
                'query' => $this->isFiltered ? $this->query['query'] : [
                    'match_all' => new \stdClass(),
                ],
            ],
        ];

        $response = $this->client->search($params);

        $data['query'] = $params;
        $data['data'] = $response['hits']['hits'][0]['_source'] ?? [];
        $data['index'] = $response['hits']['hits'][0]['_index'] ?? null;
        $data['total'] = count($response['hits']['hits']);
        $data['records'] = $response['hits']['total']['value'];

        return $data;
    }

    public function firstOrFail()
    {
        $params = [
            'index' => $this->index,
            'size' => 1,
            'body' => [
                'query' => $this->isFiltered ? $this->query['query'] : [
                    'match_all' => new \stdClass(),
                ],
            ],
        ];

        $response = $this->client->search($params);

        if ($response['hits']['total']['value'] > 0) {
            return $response['hits']['hits'][0]['_source'];
        }

        throw new ModelNotFoundException();
    }

    public function get(array $fields = ['*'], ?int $size = 10, int $page = 1): array
    {
        $from = ($page - 1) * $size;

        $params = [
            'index' => $this->index,
            'size' => $size,
            'from' => $from,
            'body' => [
                'track_total_hits' => true,
                'query' => $this->isFiltered ? $this->query['query'] : [
                    'match_all' => new \stdClass(),
                ],
            ],
            '_source' => $fields,
        ];

        if (!empty($this->query['sort'])) {
            $params['body']['sort'] = $this->query['sort'];
        }

        $response = $this->client->search($params);

        return [
            'query' => $params,
            'size' => $size,
            'from' => $from,
            'to' => (int)round($response['hits']['total']['value'] / $size),
            'page' => $page,
            'took' => $response['took'],
            'total' => $response['hits']['total']['value'],
            'data' => $response['hits']['hits'],
        ];
    }

    public function delete(string $id): bool
    {
        $params = [
            'index' => $this->index,
            'id' => $id,
        ];

        $response = $this->client->delete($params);

        return $response['result'] === 'deleted';
    }

    public function whereDateBetween(string $field, string $from, string $to): self
    {
        $this->isFiltered = true;

        $this->query['query']['bool']['should'][] = [
            'range' => [
                $field => [
                    'gte' => $from,
                    'lt' => $to,
                ],
            ],
        ];

        return $this;
    }

    public function randomize(): self
    {
        $this->isRandomized = true;

        return $this;
    }

    public function orderByGeo(
        string $field,
        string $latitude,
        string $longitude,
        string $direction = 'asc',
        string $format = 'arc'
    ): self {
        $sort = [
            '_geo_distance' => [
                $field => [
                    'lat' => $latitude,
                    'lon' => $longitude,
                ],
                'order' => $direction,
                'unit' => 'km',
                'mode' => 'min',
                'distance_type' => $format,
                'ignore_unmapped' => true,
            ],
        ];

        $this->query['sort'][] = $sort;

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc', string $format = 'strict_date'): self
    {
        $sort = [
            $field => [
                'order' => $direction,
                'format' => $format,
            ],
        ];

        $this->query['sort'][] = $sort;

        //        if ($this->isRandomized) {
        //            $this->query['sort']['random_score'] = [];
        //        }

        return $this;
    }

    public function join(string $index, string $type, string $field1, string $field2): self
    {
        $this->query = [
            'join' => [
                'indices' => ['my_index', $index],
                'on' => [
                    $this->index => $field1,
                    $index => $field2,
                ],
                'request' => [
                    'query' => [
                        'match_all' => [],
                    ],
                ],
                'type' => $type,
            ],
        ];

        return $this;
    }

    public function resetQuery(): self
    {
        $this->query = [
            'match_all' => [],
        ];

        $this->join = null;

        return $this;
    }
}
