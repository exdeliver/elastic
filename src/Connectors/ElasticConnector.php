<?php

namespace Exdeliver\Elastic\Connectors;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Exdeliver\Elastic\Actions\EnvironmentChecker;

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
            ->setHosts([config('services.elasticsearch.url')])
            ->setSSLVerification(false);

        if (config('services.elasticsearch.auth', false)) {
            $clientBuilder->setBasicAuthentication(
                config('services.elasticsearch.username'),
                config('services.elasticsearch.password'),
            );
        }

        return $clientBuilder;
    }
}
