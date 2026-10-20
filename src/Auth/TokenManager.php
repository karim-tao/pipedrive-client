<?php

namespace Pipedrive\Client\Auth;

use Pipedrive\Client\Auth\Grants\Grant;
use Pipedrive\Client\Auth\Grants\RefreshTokenGrant;
use Pipedrive\Client\Events\TokenRefreshed;
use Pipedrive\Client\Exceptions\PipedriveAuthenticationException;
use Pipedrive\Client\Http\Transporter;
use Psr\EventDispatcher\EventDispatcherInterface;

final class TokenManager
{
    public function __construct(
        private readonly PipedriveCredentials $credentials,
        private readonly Transporter $transporter,
        private readonly ?Grant $grant = null,
        private ?Token $token = null,
        private readonly ?EventDispatcherInterface $events = null,
    ) {}

    public function ensure(): Token
    {
        if ($this->token !== null && ! $this->token->isExpired()) {
            return $this->token;
        }

        if ($this->token !== null) {
            return $this->refresh();
        }

        if ($this->grant === null) {
            throw new PipedriveAuthenticationException('Pipedrive cannot authenticate: no grant configured and no token to refresh.');
        }

        return $this->store($this->requestToken($this->grant));
    }

    public function refresh(): Token
    {
        if ($this->token === null) {
            return $this->ensure();
        }

        return $this->store($this->requestToken(new RefreshTokenGrant($this->token->refreshToken())));
    }

    public function current(): ?Token
    {
        return $this->token;
    }

    private function requestToken(Grant $grant): Token
    {
        $response = $this->transporter->response(
            $this->transporter->send('POST', 'https://oauth.pipedrive.com/oauth/token', [
                'form_params' => $grant->parameters(),
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->credentials->clientId . ':' . $this->credentials->clientSecret),
                ],
            ])
        );

        return Token::fromOAuthResponse($response->body());
    }

    private function store(Token $token): Token
    {
        $this->token = $token;

        $this->events?->dispatch(new TokenRefreshed($token));

        return $token;
    }
}
