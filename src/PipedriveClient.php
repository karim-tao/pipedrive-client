<?php

namespace Pipedrive\Client;

use Pipedrive\Client\Auth\Token;
use Pipedrive\Client\Auth\TokenManager;
use Pipedrive\Client\Endpoints\UsersEndpoint;
use Pipedrive\Client\Http\Transporter;

final class PipedriveClient
{
    private function __construct(
        private readonly Transporter $transporter,
        private readonly TokenManager $tokens,
    ) {}

    public static function factory(): Factory
    {
        return new Factory(static fn (mixed ...$args): self => new self(...$args));
    }

    public function users(): UsersEndpoint
    {
        return new UsersEndpoint($this);
    }

    public function authenticate(): Token
    {
        return $this->tokens->ensure();
    }

    public function token(): ?Token
    {
        return $this->tokens->current();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function send(string $method, string $uri, array $options = []): PipedriveResponse
    {
        $token = $this->tokens->ensure();
        $response = $this->transporter->send($method, $uri, $options, $token->accessToken(), $token->baseUrl());

        if ($this->transporter->isExpiredTokenResponse($response)) {
            $token = $this->tokens->refresh();
            $response = $this->transporter->send($method, $uri, $options, $token->accessToken(), $token->baseUrl());
        }

        return $this->transporter->response($response);
    }
}
