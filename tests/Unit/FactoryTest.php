<?php

use Pipedrive\Client\Events\TokenRefreshed;
use Pipedrive\Client\PipedriveClient;
use Pipedrive\Client\Tests\Support\EventRecorder;
use Pipedrive\Client\Tests\Support\FakeHttpClient;

test('make requires client credentials', function () {
    expect(fn () => PipedriveClient::factory()->make())
        ->toThrow(InvalidArgumentException::class, 'Pipedrive client credentials are required.');
});

test('make requires an authorization code or a stored token', function () {
    expect(fn () => PipedriveClient::factory()->withClientCredentials('id', 'secret')->make())
        ->toThrow(InvalidArgumentException::class, 'Pipedrive authentication is required.');
});

test('make builds a client using PSR discovery when no transport is injected', function () {
    $client = PipedriveClient::factory()
        ->withClientCredentials('id', 'secret')
        ->withToken('access', time() + 3600, 'refresh', 'https://acme.pipedrive.com')
        ->make();

    expect($client)->toBeInstanceOf(PipedriveClient::class)
        ->and($client->token()?->baseUrl())->toBe('https://acme.pipedrive.com');
});

test('make hands the event dispatcher to the token manager', function () {
    $events = new EventRecorder;

    pipedriveFactory(new FakeHttpClient([tokenResponse()]))
        ->withAuthorizationCodeAuth('the-code', 'https://app.test/callback')
        ->withEventDispatcher($events)
        ->make()
        ->authenticate();

    expect($events->events)->toHaveCount(1)
        ->and($events->events[0])->toBeInstanceOf(TokenRefreshed::class);
});

test('make sends the calls to the default address when the token has none', function () {
    $http = new FakeHttpClient([httpResponse(200, [], '{"success":true,"data":{"id":5}}')]);

    pipedriveFactory($http)
        ->withToken('access', PHP_INT_MAX)
        ->make()
        ->users()
        ->me();

    expect((string) $http->requests[0]->getUri())->toBe('https://api.pipedrive.com/api/v1/users/me');
});

test('make sends the calls to the given address when the token has none', function () {
    $http = new FakeHttpClient([httpResponse(200, [], '{"success":true,"data":{"id":5}}')]);

    pipedriveFactory($http)
        ->withToken('access', PHP_INT_MAX)
        ->withBaseUrl('https://acme.pipedrive.com')
        ->make()
        ->users()
        ->me();

    expect((string) $http->requests[0]->getUri())->toBe('https://acme.pipedrive.com/api/v1/users/me');
});

test('make asks for the token at the given token url', function () {
    $http = new FakeHttpClient([tokenResponse()]);

    pipedriveFactory($http)
        ->withAuthorizationCodeAuth('the-code', 'https://app.test/callback')
        ->withTokenUrl('https://auth.example.test/token')
        ->make()
        ->authenticate();

    expect((string) $http->requests[0]->getUri())->toBe('https://auth.example.test/token');
});
