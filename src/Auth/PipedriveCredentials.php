<?php

namespace Pipedrive\Client\Auth;

final readonly class PipedriveCredentials
{
    public function __construct(
        public string $clientId,
        public string $clientSecret,
    ) {}
}
