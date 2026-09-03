<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Drivers;

use Duxbo\Seo\Contracts\AiDriver;
use Duxbo\Seo\Data\AiRequest;
use Duxbo\Seo\Exceptions\AiRequestFailed;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Shared HTTP handling for the provider drivers.
 *
 * Every driver talks plain documented REST through Laravel's own client. No
 * vendor SDK is required or even suggested: an SDK would be one more library
 * whose abandonment, or whose next major, becomes this package's problem — and
 * three of them would be three such problems for a feature that is off by
 * default.
 */
abstract class HttpDriver implements AiDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected readonly Http $http,
        protected readonly array $config,
    ) {
    }

    protected function client(): PendingRequest
    {
        return $this->http
            ->timeout((int) ($this->config['timeout'] ?? 30))
            // Retry only on transport failures and rate limits; a schema
            // rejection would fail identically every time.
            ->retry(
                (int) ($this->config['retries'] ?? 2),
                200,
                static fn (\Throwable $e, PendingRequest $request): bool => $e instanceof \Illuminate\Http\Client\ConnectionException
                    || ($e instanceof \Illuminate\Http\Client\RequestException && in_array($e->response->status(), [429, 500, 502, 503, 529], true)),
                throw: false,
            )
            ->acceptJson();
    }

    protected function model(AiRequest $request): string
    {
        $model = $request->metadata['model'] ?? $this->config['model'] ?? null;

        if (! is_string($model) || $model === '') {
            throw AiRequestFailed::noModel($this->name());
        }

        return $model;
    }

    protected function apiKey(): string
    {
        $key = $this->config['key'] ?? null;

        if (! is_string($key) || $key === '') {
            throw AiRequestFailed::noApiKey($this->name());
        }

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        if ($response->failed()) {
            // The body can echo the prompt back; the key never appears in it,
            // but the message is still truncated so a log line stays readable.
            throw AiRequestFailed::http(
                $this->name(),
                $response->status(),
                mb_substr((string) $response->body(), 0, 500),
            );
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw AiRequestFailed::unreadable($this->name());
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonString(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw AiRequestFailed::notStructured($this->name());
        }

        return $decoded;
    }
}
