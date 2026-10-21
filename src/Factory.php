<?php

namespace Pipedrive\Client;

use Closure;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use InvalidArgumentException;
use Pipedrive\Client\Auth\Grants\AuthorizationCodeGrant;
use Pipedrive\Client\Auth\Grants\Grant;
use Pipedrive\Client\Auth\PipedriveCredentials;
use Pipedrive\Client\Auth\Token;
use Pipedrive\Client\Auth\TokenManager;
use Pipedrive\Client\Http\Transporter;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final class Factory
{
    private ?string $clientId = null;

    private ?string $clientSecret = null;

    private ?Grant $grant = null;

    private ?Token $token = null;

    private string $baseUrl = 'https://api.pipedrive.com';

    private ?string $tokenUrl = null;

    private ?EventDispatcherInterface $events = null;

    private ?ClientInterface $http = null;

    private ?RequestFactoryInterface $requestFactory = null;

    private ?StreamFactoryInterface $streamFactory = null;

    private ?UriFactoryInterface $uriFactory = null;

    /**
     * @param  Closure(mixed ...$args): PipedriveClient  $instantiator
     */
    public function __construct(private readonly Closure $instantiator) {}

    public function withClientCredentials(string $clientId, string $clientSecret): self
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;

        return $this;
    }

    public function withAuthorizationCodeAuth(string $code, string $redirectUri): self
    {
        $this->grant = new AuthorizationCodeGrant($code, $redirectUri);

        return $this;
    }

    public function withToken(string $accessToken, int $expiresAt, ?string $refreshToken = null, ?string $baseUrl = null): self
    {
        $this->token = Token::fromValues($accessToken, $expiresAt, $refreshToken, $baseUrl);

        return $this;
    }

    public function withBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function withTokenUrl(string $tokenUrl): self
    {
        $this->tokenUrl = $tokenUrl;

        return $this;
    }

    public function withEventDispatcher(EventDispatcherInterface $events): self
    {
        $this->events = $events;

        return $this;
    }

    public function withHttpClient(ClientInterface $http): self
    {
        $this->http = $http;

        return $this;
    }

    public function withHttpFactories(RequestFactoryInterface $requestFactory, StreamFactoryInterface $streamFactory, UriFactoryInterface $uriFactory): self
    {
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->uriFactory = $uriFactory;

        return $this;
    }

    public function make(): PipedriveClient
    {
        if ($this->clientId === null || $this->clientSecret === null) {
            throw new InvalidArgumentException('Pipedrive client credentials are required. Call withClientCredentials() before make().');
        }

        if ($this->grant === null && $this->token === null) {
            throw new InvalidArgumentException('Pipedrive authentication is required. Call withAuthorizationCodeAuth() or withToken() before make().');
        }

        $transporter = new Transporter(
            http: $this->http ?? Psr18ClientDiscovery::find(),
            requestFactory: $this->requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            streamFactory: $this->streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            uriFactory: $this->uriFactory ?? Psr17FactoryDiscovery::findUriFactory(),
            baseUrl: $this->baseUrl,
        );

        return ($this->instantiator)(
            transporter: $transporter,
            tokens: new TokenManager(
                credentials: new PipedriveCredentials($this->clientId, $this->clientSecret),
                transporter: $transporter,
                tokenUrl: $this->tokenUrl ?? 'https://oauth.pipedrive.com/oauth/token',
                grant: $this->grant,
                token: $this->token,
                events: $this->events,
            ),
        );
    }
}
