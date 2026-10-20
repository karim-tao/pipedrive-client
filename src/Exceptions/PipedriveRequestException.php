<?php

namespace Pipedrive\Client\Exceptions;

use RuntimeException;

final class PipedriveRequestException extends RuntimeException implements PipedriveException
{
    public function __construct(
        private readonly int $statusCode,
        private readonly mixed $responseBody,
        string $message = 'Pipedrive request failed.',
    ) {
        parent::__construct($message, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function responseBody(): mixed
    {
        return $this->responseBody;
    }
}
