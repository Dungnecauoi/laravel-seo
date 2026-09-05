<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai;

use Closure;
use Duxbo\Seo\Ai\Drivers\ClaudeDriver;
use Duxbo\Seo\Ai\Drivers\GeminiDriver;
use Duxbo\Seo\Ai\Drivers\GroqDriver;
use Duxbo\Seo\Ai\Drivers\NullDriver;
use Duxbo\Seo\Ai\Drivers\OpenAiDriver;
use Duxbo\Seo\Ai\Drivers\OpenRouterDriver;
use Duxbo\Seo\Contracts\AiDriver;
use Duxbo\Seo\Contracts\ResetsBetweenRequests;
use Duxbo\Seo\Data\AiRequest;
use Duxbo\Seo\Data\AiResponse;
use Duxbo\Seo\Events\AiRequestSent;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as Http;
use InvalidArgumentException;

/**
 * Chooses and drives a language model, the way Storage and Cache choose a disk.
 *
 * The default driver is `null`: installing a package must never start billing
 * anyone, so AI stays inert until a real driver is configured.
 */
final class AiManager implements ResetsBetweenRequests
{
    /**
     * Built drivers, keyed by name — not `$custom` below, which is a
     * registered *factory* an application is expected to set up once, in a
     * service provider, the same as `Blade::directive()`. `$resolved`
     * memoizes what that factory (or a built-in driver's config) actually
     * produced, which is exactly the kind of thing a long-running worker
     * needs to rebuild once a new request starts, in case the config it
     * closed over has changed since.
     *
     * @var array<string, AiDriver>
     */
    private array $resolved = [];

    /** @var array<string, Closure(Container, array<string, mixed>): AiDriver> */
    private array $custom = [];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
        private readonly Cache $cache,
        private readonly Dispatcher $events,
        private readonly AiBudget $budget,
        private readonly PromptLibrary $prompts,
    ) {
    }

    public function driver(?string $name = null): AiDriver
    {
        $name ??= (string) $this->config->get('seo.ai.default', 'null');

        return $this->resolved[$name] ??= $this->resolve($name);
    }

    /**
     * Register a driver of your own — Ollama, a local model, an internal API.
     *
     * @param  Closure(Container, array<string, mixed>): AiDriver  $factory
     */
    public function extend(string $name, Closure $factory): self
    {
        $this->custom[$name] = $factory;
        unset($this->resolved[$name]);

        return $this;
    }

    /**
     * Registered factories in `$custom` are left alone — an application
     * sets those up once, expecting them to stay for the process's life,
     * the same as a route or a Blade directive. Only `$resolved` is
     * dropped, so the next `driver()` call rebuilds from whatever the
     * current config says rather than whatever it said when this worker
     * first booted.
     */
    public function resetForNewRequest(): void
    {
        $this->resolved = [];
    }

    public function prompts(): PromptLibrary
    {
        return $this->prompts;
    }

    /**
     * Send a request, with caching, budget enforcement and logging around it.
     */
    public function complete(AiRequest $request, ?string $driver = null, ?string $purpose = null): AiResponse
    {
        $cached = $this->cached($request);

        if ($cached !== null) {
            return $cached;
        }

        $this->budget->assertWithinBudget();

        $instance = $this->driver($driver);

        try {
            $response = $instance->complete($request);
        } catch (\Throwable $e) {
            $this->budget->recordFailure($instance->name(), $e->getMessage(), $purpose);

            throw $e;
        }

        $this->budget->record($response, $purpose);
        $this->events->dispatch(new AiRequestSent($request, $response, $purpose));
        $this->remember($request, $response);

        return $response;
    }

    /**
     * Suggest a title and description for a piece of content.
     *
     * @return array{title?: string, description?: string}
     */
    public function suggestMeta(string $content, ?string $keyword = null, ?string $locale = null): array
    {
        $response = $this->complete(
            $this->prompts->meta($content, $keyword, $locale),
            purpose: 'meta',
        );

        /** @var array{title?: string, description?: string} $result */
        $result = $response->content;

        return $result;
    }

    /**
     * @return list<string>
     */
    public function suggestKeywords(string $content, ?string $locale = null): array
    {
        $response = $this->complete(
            $this->prompts->keywords($content, $locale),
            purpose: 'keywords',
        );

        $keywords = $response->get('keywords', []);

        return is_array($keywords)
            ? array_values(array_filter($keywords, 'is_string'))
            : [];
    }

    private function resolve(string $name): AiDriver
    {
        if (isset($this->custom[$name])) {
            return ($this->custom[$name])($this->container, $this->driverConfig($name));
        }

        $config = $this->driverConfig($name);
        $http = $this->container->make(Http::class);

        return match ($name) {
            'null' => new NullDriver(),
            'claude' => new ClaudeDriver($http, $config),
            'openai' => new OpenAiDriver($http, $config),
            'gemini' => new GeminiDriver($http, $config),
            'groq' => new GroqDriver($http, $config),
            'openrouter' => new OpenRouterDriver($http, $config),
            default => throw new InvalidArgumentException(
                "No SEO AI driver named [{$name}]. Register one with Seo::ai()->extend()."
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function driverConfig(string $name): array
    {
        /** @var array<string, mixed> $config */
        $config = $this->config->get("seo.ai.drivers.{$name}", []);

        return $config;
    }

    private function cached(AiRequest $request): ?AiResponse
    {
        $ttl = (int) $this->config->get('seo.ai.cache_ttl', 0);

        if ($ttl <= 0) {
            return null;
        }

        /** @var array<string, mixed>|null $stored */
        $stored = $this->cache->get($request->cacheKey());

        if ($stored === null) {
            return null;
        }

        // Same content, same prompt: never billed twice.
        return new AiResponse(
            content: is_array($stored['content'] ?? null) ? $stored['content'] : [],
            driver: (string) ($stored['driver'] ?? 'cache'),
            model: is_string($stored['model'] ?? null) ? $stored['model'] : null,
            fromCache: true,
        );
    }

    private function remember(AiRequest $request, AiResponse $response): void
    {
        $ttl = (int) $this->config->get('seo.ai.cache_ttl', 0);

        if ($ttl <= 0) {
            return;
        }

        $this->cache->put($request->cacheKey(), [
            'content' => $response->content,
            'driver' => $response->driver,
            'model' => $response->model,
        ], $ttl);
    }
}
