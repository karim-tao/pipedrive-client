<?php

use Pipedrive\Client\Auth\Token;
use Pipedrive\Client\Exceptions\PipedriveAuthenticationException;

test('it reads the token, the refresh token and the api domain of the company as its base url', function () {
    $token = Token::fromOAuthResponse((object) [
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'expires_in' => 3599,
        'api_domain' => 'https://acme.pipedrive.com',
    ]);

    expect($token->accessToken())->toBe('access')
        ->and($token->refreshToken())->toBe('refresh')
        ->and($token->baseUrl())->toBe('https://acme.pipedrive.com')
        ->and($token->expiresAt())->toBeGreaterThan(time() + 3500)
        ->and($token->isExpired())->toBeFalse();
});

test('it rejects an incomplete authentication response', function (mixed $response, string $message) {
    expect(fn () => Token::fromOAuthResponse($response))->toThrow(PipedriveAuthenticationException::class, $message);
})->with([
    'not an object' => ['nope', 'did not include an access token'],
    'no access token' => [(object) ['expires_in' => 60, 'refresh_token' => 'r', 'api_domain' => 'https://a.pipedrive.com'], 'did not include an access token'],
    'no expiration' => [(object) ['access_token' => 'a', 'refresh_token' => 'r', 'api_domain' => 'https://a.pipedrive.com'], 'did not include a valid expiration'],
]);

test('a response without a refresh token or an api domain leaves them empty', function () {
    $token = Token::fromOAuthResponse((object) ['access_token' => 'a', 'expires_in' => 60, 'refresh_token' => '', 'api_domain' => '']);

    expect($token->refreshToken())->toBeNull()
        ->and($token->baseUrl())->toBeNull();
});

test('a token past its expiry is expired', function () {
    expect(Token::fromValues('a', time() - 1, 'r', 'https://a.pipedrive.com')->isExpired())->toBeTrue();
});
