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
        ->and($client->token()?->apiDomain())->toBe('https://acme.pipedrive.com');
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
