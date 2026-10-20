<?php

namespace Pipedrive\Client\Events;

use Pipedrive\Client\Auth\Token;

final readonly class TokenRefreshed
{
    public function __construct(public Token $token) {}
}
