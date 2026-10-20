<?php

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Pipedrive\Client\Factory;
use Pipedrive\Client\PipedriveClient;
use Pipedrive\Client\Tests\Support\FakeHttpClient;

pest()->extend(TestCase::class)->in('Unit');

/**
 * @param  array<string, string|array<int, string>>  $headers
 */
function httpResponse(int $status, array $headers, string $body): Response
{
    return new Response($status, $headers, $body);
}

function tokenResponse(string $accessToken = 'new-access', string $refreshToken = 'new-refresh'): Response
{
    return new Response(200, [], (string) json_encode([
        'access_token' => $accessToken,
        'token_type' => 'Bearer',
        'refresh_token' => $refreshToken,
        'scope' => 'base',
        'expires_in' => 3599,
        'api_domain' => 'https://acme.pipedrive.com',
    ]));
}

function pipedriveFactory(FakeHttpClient $http): Factory
{
    $factory = new Psr17Factory;

    return PipedriveClient::factory()
        ->withClientCredentials('client-id', 'client-secret')
        ->withHttpClient($http)
        ->withHttpFactories($factory, $factory, $factory);
}

/**
 * @param  array<int, Response>  $responses
 * @return array{0: PipedriveClient, 1: FakeHttpClient}
 */
function pipedriveClientWith(array $responses, int $expiresAt = PHP_INT_MAX): array
{
    $http = new FakeHttpClient($responses);

    return [
        pipedriveFactory($http)
            ->withToken('stored-access', $expiresAt, 'stored-refresh', 'https://acme.pipedrive.com')
            ->make(),
        $http,
    ];
}
