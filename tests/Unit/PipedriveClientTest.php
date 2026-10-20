<?php

use Pipedrive\Client\Endpoints\UsersEndpoint;
use Pipedrive\Client\Exceptions\PipedriveRequestException;
use Pipedrive\Client\PipedriveResponse;
use Pipedrive\Client\Tests\Support\FakeHttpClient;

test('it exposes the users endpoint', function () {
    [$client] = pipedriveClientWith([]);

    expect($client->users())->toBeInstanceOf(UsersEndpoint::class);
});

test('send calls the api domain of the company with the bearer token', function () {
    [$client, $http] = pipedriveClientWith([httpResponse(200, ['X-Test' => 'yes'], '{"success":true,"data":[]}')]);

    $response = $client->send('GET', '/api/v2/deals', ['query' => ['limit' => 500]]);

    expect($response)->toBeInstanceOf(PipedriveResponse::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers()['X-Test'])->toBe(['yes'])
        ->and($response->body()->success)->toBeTrue()
        ->and((string) $http->requests[0]->getUri())->toBe('https://acme.pipedrive.com/api/v2/deals?limit=500')
        ->and($http->requests[0]->getHeaderLine('Authorization'))->toBe('Bearer stored-access')
        ->and($http->requests[0]->getHeaderLine('Accept'))->toBe('application/json');
});

test('send posts json bodies', function () {
    [$client, $http] = pipedriveClientWith([httpResponse(201, [], '{"success":true}')]);

    $client->send('POST', '/api/v1/notes', ['json' => ['content' => 'Hello']]);

    expect($http->requests[0]->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and((string) $http->requests[0]->getBody())->toBe('{"content":"Hello"}');
});

test('send refreshes the token once when pipedrive rejects it', function () {
    [$client, $http] = pipedriveClientWith([
        httpResponse(401, [], '{"success":false,"error":"unauthorized access"}'),
        tokenResponse(),
        httpResponse(200, [], '{"success":true}'),
    ]);

    $client->send('GET', '/api/v1/users/me');

    expect($http->requests[2]->getHeaderLine('Authorization'))->toBe('Bearer new-access')
        ->and($client->token()?->refreshToken())->toBe('new-refresh');
});

test('send refreshes an expired token before calling pipedrive', function () {
    [$client, $http] = pipedriveClientWith([tokenResponse(), httpResponse(200, [], '{"success":true}')], expiresAt: time() - 10);

    $client->send('GET', '/api/v1/users/me');

    expect((string) $http->requests[0]->getUri())->toBe('https://oauth.pipedrive.com/oauth/token')
        ->and($http->requests[1]->getHeaderLine('Authorization'))->toBe('Bearer new-access');
});

test('send throws the pipedrive error', function () {
    [$client] = pipedriveClientWith([httpResponse(403, [], '{"success":false,"error":"Scope and URL mismatch","errorCode":403}')]);

    expect(fn () => $client->send('POST', '/api/v1/pipelines'))
        ->toThrow(PipedriveRequestException::class, 'Pipedrive request failed with status 403: Scope and URL mismatch');
});

test('send keeps a non json error body', function () {
    [$client] = pipedriveClientWith([httpResponse(429, [], '<html>Too Many Requests</html>')]);

    expect(fn () => $client->send('GET', '/api/v2/deals'))->toThrow(function (PipedriveRequestException $exception) {
        expect($exception->statusCode())->toBe(429)
            ->and($exception->responseBody())->toBe('<html>Too Many Requests</html>')
            ->and($exception->getMessage())->toBe('Pipedrive request failed with status 429.');
    });
});

test('send returns null for an empty body', function () {
    [$client] = pipedriveClientWith([httpResponse(204, [], '')]);

    expect($client->send('DELETE', '/api/v1/notes/1')->body())->toBeNull();
});

test('send rejects a successful body that is not json', function () {
    [$client] = pipedriveClientWith([httpResponse(200, [], 'not json')]);

    expect(fn () => $client->send('GET', '/api/v1/users/me'))
        ->toThrow(RuntimeException::class, 'Pipedrive response body is not valid JSON.');
});

test('authenticate returns the current token', function () {
    $http = new FakeHttpClient([tokenResponse()]);

    $token = pipedriveFactory($http)
        ->withAuthorizationCodeAuth('the-code', 'https://app.test/callback')
        ->make()
        ->authenticate();

    expect($token->accessToken())->toBe('new-access');
});
