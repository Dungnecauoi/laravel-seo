<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Drivers;

use Illuminate\Http\Client\PendingRequest;

/**
 * OpenRouter's OpenAI-compatible Chat Completions endpoint — see
 * {@see OpenAiCompatibleDriver} for the request and response handling
 * itself. OpenRouter proxies to whichever underlying model
 * `seo.ai.drivers.openrouter.model` names (`'anthropic/claude-sonnet-5'`,
 * `'meta-llama/llama-3.3-70b'`, and so on); structured output support
 * depends on that model, not on OpenRouter itself.
 *
 * `referer` and `title` are optional and off by default — OpenRouter's own
 * docs ask for them for its public leaderboard attribution, not for the
 * request to function.
 */
final class OpenRouterDriver extends OpenAiCompatibleDriver
{
    public function name(): string
    {
        return 'openrouter';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://openrouter.ai/api/v1';
    }

    protected function client(): PendingRequest
    {
        $headers = array_filter([
            'HTTP-Referer' => $this->config['referer'] ?? null,
            'X-Title' => $this->config['title'] ?? null,
        ]);

        return $headers === [] ? parent::client() : parent::client()->withHeaders($headers);
    }
}
