<?php

namespace Pipedrive\Client\Http;

use Pipedrive\Client\Exceptions\PipedriveRequestException;
use Pipedrive\Client\PipedriveResponse;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use RuntimeException;

final readonly class Transporter
{
    public function __construct(
        private ClientInterface $http,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private UriFactoryInterface $uriFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function send(string $method, string $url, array $options = [], ?string $bearer = null): ResponseInterface
    {
        return $this->http->sendRequest($this->buildRequest($method, $url, $options, $bearer));
    }

    public function response(ResponseInterface $response): PipedriveResponse
    {
        $body = $this->decode($response);

        if ($response->getStatusCode() >= 400) {
            throw new PipedriveRequestException(
                statusCode: $response->getStatusCode(),
                responseBody: $body,
                message: $this->exceptionMessage($response, $body),
            );
        }

        return new PipedriveResponse(
            body: $body,
            statusCode: $response->getStatusCode(),
            headers: $response->getHeaders(),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function buildRequest(string $method, string $url, array $options, ?string $bearer): RequestInterface
    {
        $uri = $this->uriFactory->createUri($url);

        if (isset($options['query']) && is_array($options['query']) && $options['query'] !== []) {
            $uri = $uri->withQuery(http_build_query($options['query']));
        }

        $request = $this->requestFactory
            ->createRequest($method, $uri)
            ->withHeader('Accept', 'application/json');

        if ($bearer !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $bearer);
        }

        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $request = $request->withHeader((string) $name, (string) $value);
            }
        }

        if (isset($options['json'])) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream((string) json_encode($options['json'])));
        }

        if (isset($options['form_params']) && is_array($options['form_params'])) {
            $request = $request
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody($this->streamFactory->createStream(http_build_query($options['form_params'])));
        }

        return $request;
    }

    private function exceptionMessage(ResponseInterface $response, mixed $body): string
    {
        $message = is_object($body) ? ($body->error ?? null) : null;

        if (is_string($message) && $message !== '') {
            return 'Pipedrive request failed with status ' . $response->getStatusCode() . ': ' . $message;
        }

        return 'Pipedrive request failed with status ' . $response->getStatusCode() . '.';
    }

    private function decode(ResponseInterface $response): mixed
    {
        $body = (string) $response->getBody();

        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        if ($response->getStatusCode() >= 400) {
            return $body;
        }

        throw new RuntimeException('Pipedrive response body is not valid JSON.');
    }
}
