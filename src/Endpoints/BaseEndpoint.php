<?php

namespace Pipedrive\Client\Endpoints;

use Pipedrive\Client\PipedriveClient;

abstract class BaseEndpoint
{
    final public function __construct(protected readonly PipedriveClient $client) {}

    /**
     * @param  array<string, mixed>  $options
     */
    protected function request(string $method, string $uri, array $options = []): mixed
    {
        return $this->client->send($method, $uri, $options)->body();
    }
}
