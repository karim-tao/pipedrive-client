<?php

namespace Pipedrive\Client\Auth;

use Pipedrive\Client\Exceptions\PipedriveAuthenticationException;

final readonly class Token
{
    public function __construct(
        private string $accessToken,
        private int $expiresAt,
        private string $refreshToken,
        private string $apiDomain,
    ) {}

    public static function fromOAuthResponse(mixed $response): self
    {
        $accessToken = is_object($response) ? ($response->access_token ?? null) : null;
        $expiresIn = is_object($response) ? ($response->expires_in ?? null) : null;
        $refreshToken = is_object($response) ? ($response->refresh_token ?? null) : null;
        $apiDomain = is_object($response) ? ($response->api_domain ?? null) : null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new PipedriveAuthenticationException('Pipedrive authentication response did not include an access token.');
        }

        if (! is_numeric($expiresIn) || (int) $expiresIn < 1) {
            throw new PipedriveAuthenticationException('Pipedrive authentication response did not include a valid expiration.');
        }

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new PipedriveAuthenticationException('Pipedrive authentication response did not include a refresh token.');
        }

        if (! is_string($apiDomain) || $apiDomain === '') {
            throw new PipedriveAuthenticationException('Pipedrive authentication response did not include an api domain.');
        }

        return new self($accessToken, time() + (int) $expiresIn, $refreshToken, $apiDomain);
    }

    public static function fromValues(string $accessToken, int $expiresAt, string $refreshToken, string $apiDomain): self
    {
        return new self($accessToken, $expiresAt, $refreshToken, $apiDomain);
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function refreshToken(): string
    {
        return $this->refreshToken;
    }

    public function expiresAt(): int
    {
        return $this->expiresAt;
    }

    public function apiDomain(): string
    {
        return $this->apiDomain;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= time();
    }
}
