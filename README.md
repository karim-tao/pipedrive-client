# Pipedrive Client

[![Tests](https://github.com/karim-tao/pipedrive-client/actions/workflows/tests.yml/badge.svg)](https://github.com/karim-tao/pipedrive-client/actions/workflows/tests.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/karim-tao/pipedrive-client)](https://packagist.org/packages/karim-tao/pipedrive-client)
[![License](https://img.shields.io/packagist/l/karim-tao/pipedrive-client)](https://packagist.org/packages/karim-tao/pipedrive-client)

A framework-agnostic PHP client for the Pipedrive API, built on PSR-18, bring your own HTTP client, everything else is wired for you.

In this example, we build a client from a stored token and read the authorized user, the token is refreshed for you:

```php
use Pipedrive\Client\PipedriveClient;

$pipedrive = PipedriveClient::factory()
    ->withClientCredentials($clientId, $clientSecret)
    ->withToken($accessToken, $expiresAt, $refreshToken, $baseUrl)
    ->make();

$user = $pipedrive->users()->me();
```

> Using Laravel? See [`karim-tao/pipedrive-laravel`](https://github.com/karim-tao/pipedrive-laravel).

## Installation

> Requires PHP 8.3+.

You can install the package via composer:

```bash
composer require karim-tao/pipedrive-client
```

Ensure the [`php-http/discovery`](https://github.com/php-http/discovery) composer plugin is allowed to run, or install a [PSR-18](https://www.php-fig.org/psr/psr-18/) client manually if your project does not already have one:

```bash
composer require guzzlehttp/guzzle
```

## Usage

### Creating a client

```php
use Pipedrive\Client\PipedriveClient;

$pipedrive = PipedriveClient::factory()
    ->withClientCredentials($clientId, $clientSecret) // required
    ->withAuthorizationCodeAuth($code, $redirectUri)  // required (see Authentication for more details)
    ->withBaseUrl('https://api.pipedrive.com')        // optional
    ->withTokenUrl($tokenUrl)                         // optional
    ->withEventDispatcher($events)                    // optional (PSR-14)
    ->withHttpClient($client)                         // optional (PSR-18)
    ->make();
```

### Authentication

A client always needs your Pipedrive app credentials plus one authentication method, slotted into the builder above.
The token manager exchanges the code and refreshes the token for you.

| Method             | Builder call                                                     | Use when                                 |
|--------------------|------------------------------------------------------------------|------------------------------------------|
| Authorization code | `->withAuthorizationCodeAuth($code, $redirectUri)`               | right after the user authorised your app |
| Token              | `->withToken($accessToken, $expiresAt, $refreshToken, $baseUrl)` | reuse a token you stored                 |

Read (and persist) the current token:

```php
$token = $pipedrive->authenticate();
$token = $pipedrive->token();

$token->accessToken();
$token->refreshToken();
$token->expiresAt();
$token->baseUrl();
```

The token's base URL is the `api_domain` of the user's company, which Pipedrive returns with the token. When it is there it wins over `withBaseUrl()`, so every call goes to that company.

### Endpoints

```php
// Users
$user = $pipedrive->users()->me();
```

### Events

With an event dispatcher, the client dispatches `Pipedrive\Client\Events\TokenRefreshed` whenever it obtains a token: on the code exchange and on every refresh. Pipedrive gives a new refresh token on every refresh, so listen to it to store the new pair as soon as Pipedrive issues it:

```php
$event->token->accessToken();
$event->token->refreshToken();
$event->token->expiresAt();
$event->token->baseUrl();
```

### Raw requests

For anything not covered by an endpoint, send a request directly:

```php
$response = $pipedrive->send('GET', '/api/v2/deals', ['query' => ['limit' => 500]]);

$response->statusCode();
$response->body();
$response->headers();
```

## How it works

`PipedriveClient` wraps a transporter over your PSR-18 client (or one discovered via `php-http/discovery`) and a token manager that:

- exchanges the authorization code, or reuses a stored token, authenticating with HTTP Basic on `https://oauth.pipedrive.com/oauth/token`;
- refreshes the token automatically when it expires, and once more when Pipedrive answers 401;
- dispatches `TokenRefreshed` through the PSR-14 event dispatcher, when you pass one, every time it obtains a token.

Every endpoint is a small typed wrapper over `send()`, which calls the base URL of the token, or the one of the factory.

## Testing

```bash
composer test
```

To test code that uses the client without hitting Pipedrive, inject a stub PSR-18 client:

```php
$pipedrive = PipedriveClient::factory()
    ->withClientCredentials('id', 'secret')
    ->withToken('access', time() + 3600, 'refresh', 'https://acme.pipedrive.com')
    ->withHttpClient($stubPsr18Client)
    ->make();
```

## License

Pipedrive Client is open-sourced software licensed under the [MIT license](LICENSE.md).
