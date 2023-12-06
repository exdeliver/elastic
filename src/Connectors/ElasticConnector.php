<?php

namespace Exdeliver\Elastic\Connectors;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

class ElasticConnector
{
    public Client $client;

    public function __construct(?ClientBuilder $client = null)
    {
        $this->client = ($client ?? $this->clientBuilder())->build();
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public static function make(?ClientBuilder $client = null): ElasticConnector
    {
        return new self($client);
    }

    protected function clientBuilder(): ClientBuilder
    {
        $clientBuilder = ClientBuilder::create()
            ->setHosts([config('database.connections.elasticsearch.url')])
            ->setSSLVerification(false);

        if (config('database.connections.elasticsearch.auth', false)) {
            $clientBuilder->setBasicAuthentication(
                config('database.connections.elasticsearch.username'),
                config('database.connections.elasticsearch.password'),
            );
        }

        return $clientBuilder;
    }
}
