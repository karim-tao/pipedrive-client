<?php

use Nyholm\Psr7\Factory\Psr17Factory;
use Pipedrive\Client\Auth\Grants\AuthorizationCodeGrant;
use Pipedrive\Client\Auth\Grants\Grant;
use Pipedrive\Client\Auth\PipedriveCredentials;
use Pipedrive\Client\Auth\Token;
use Pipedrive\Client\Auth\TokenManager;
use Pipedrive\Client\Events\TokenRefreshed;
use Pipedrive\Client\Exceptions\PipedriveAuthenticationException;
use Pipedrive\Client\Http\Transporter;
use Pipedrive\Client\Tests\Support\EventRecorder;
use Pipedrive\Client\Tests\Support\FakeHttpClient;

function tokenManager(FakeHttpClient $http, ?Grant $grant = null, ?Token $token = null, ?EventRecorder $events = null): TokenManager
{
    $factory = new Psr17Factory;

    return new TokenManager(
        new PipedriveCredentials('client-id', 'client-secret'),
        new Transporter($http, $factory, $factory, $factory),
        $grant,
        $token,
        $events,
    );
}

test('ensure exchanges the authorization code with http basic auth', function () {
    $http = new FakeHttpClient([tokenResponse()]);

    $token = tokenManager($http, grant: new AuthorizationCodeGrant('the-code', 'https://app.test/callback'))->ensure();

    $request = $http->requests[0];

    expect($token->accessToken())->toBe('new-access')
        ->and($token->apiDomain())->toBe('https://acme.pipedrive.com')
        ->and((string) $request->getUri())->toBe('https://oauth.pipedrive.com/oauth/token')
        ->and($request->getHeaderLine('Authorization'))->toBe('Basic ' . base64_encode('client-id:client-secret'))
        ->and((string) $request->getBody())->toBe('grant_type=authorization_code&code=the-code&redirect_uri=https%3A%2F%2Fapp.test%2Fcallback');
});

test('ensure returns a stored token while it is valid', function () {
    $http = new FakeHttpClient([]);
    $token = Token::fromValues('stored', time() + 3600, 'stored-refresh', 'https://acme.pipedrive.com');

    expect(tokenManager($http, token: $token)->ensure())->toBe($token)
        ->and($http->requests)->toBe([]);
});

test('ensure refreshes an expired token and keeps the new pair', function () {
    $http = new FakeHttpClient([tokenResponse()]);
    $manager = tokenManager($http, token: Token::fromValues('stale', time() - 10, 'old-refresh', 'https://acme.pipedrive.com'));

    $token = $manager->ensure();

    expect($token->accessToken())->toBe('new-access')
        ->and($token->refreshToken())->toBe('new-refresh')
        ->and($manager->current())->toBe($token)
        ->and((string) $http->requests[0]->getBody())->toBe('grant_type=refresh_token&refresh_token=old-refresh');
});

test('refresh without a token authenticates with the grant', function () {
    $http = new FakeHttpClient([tokenResponse()]);

    $token = tokenManager($http, grant: new AuthorizationCodeGrant('the-code', 'https://app.test/callback'))->refresh();

    expect($token->accessToken())->toBe('new-access');
});

test('ensure fails without a grant or a token', function () {
    expect(fn () => tokenManager(new FakeHttpClient([]))->ensure())
        ->toThrow(PipedriveAuthenticationException::class, 'no grant configured');
});

test('it dispatches every token it obtains', function () {
    $events = new EventRecorder;

    tokenManager(new FakeHttpClient([tokenResponse()]), token: Token::fromValues('stale', time() - 10, 'old-refresh', 'https://acme.pipedrive.com'), events: $events)->ensure();

    expect($events->events)->toHaveCount(1)
        ->and($events->events[0])->toBeInstanceOf(TokenRefreshed::class)
        ->and($events->events[0]->token->refreshToken())->toBe('new-refresh');
});
