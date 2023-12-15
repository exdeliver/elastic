<?php

namespace Exdeliver\Elastic\Connectors;

use App\Actions\EnvironmentChecker;
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

    public static function environment(): string
    {
        if (EnvironmentChecker::is(['develop'])) {
            return 'dev_';
        }

        if (EnvironmentChecker::is(['production'])) {
            return '';
        }

        return 'test_';
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
