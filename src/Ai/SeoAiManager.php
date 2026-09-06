<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai;

use Closure;
use Duxbo\AiCore\AiManager;
use Duxbo\AiCore\Contracts\AiDriver;
use Duxbo\Seo\Data\CheckResult;
use Duxbo\Seo\Data\SeoData;

/**
 * SEO's own thin face on the shared `duxbo/laravel-ai-core` manager.
 *
 * Every call goes through with profile: 'seo', so its spend and its config
 * overrides (`config('seo.ai_overrides')`, pushed by SeoServiceProvider
 * into `ai-core.profiles.seo`) are attributed to SEO specifically, without
 * this package — or its callers — needing to know how ai-core resolves a
 * driver. The circuit breaker is the one exception: it is shared globally
 * per driver across every profile, see `AiCircuitBreaker` in ai-core.
 */
final class SeoAiManager
{
    private const PROFILE = 'seo';

    public function __construct(
        private readonly AiManager $manager,
        private readonly PromptLibrary $prompts,
    ) {
    }

    public function driver(?string $name = null): AiDriver
    {
        return $this->manager->driver($name, self::PROFILE);
    }

    /**
     * Register a driver of your own — Ollama, a local model, an internal API.
     *
     * @param  Closure(\Illuminate\Contracts\Container\Container, array<string, mixed>): AiDriver  $factory
     */
    public function extend(string $name, Closure $factory): self
    {
        $this->manager->extend($name, $factory);

        return $this;
    }

    public function prompts(): PromptLibrary
    {
        return $this->prompts;
    }

    /**
     * Suggest a title and description for a piece of content.
     *
     * `$current` and `$siteBrand` are optional grounding context — passing
     * them does not change the output shape, only how well-informed the
     * suggestion is.
     *
     * @return array{title?: string, description?: string}
     */
    public function suggestMeta(
        string $content,
        ?string $keyword = null,
        ?string $locale = null,
        ?SeoData $current = null,
        ?string $siteBrand = null,
    ): array {
        $response = $this->manager->complete(
            $this->prompts->meta($content, $keyword, $locale, $current, $siteBrand),
            profile: self::PROFILE,
            purpose: 'meta',
        );

        /** @var array{title?: string, description?: string} $result */
        $result = $response->content;

        return $result;
    }

    /**
     * Suggest a title and description that specifically address real
     * content-analysis findings, rather than writing generic meta from
     * scratch — the same shape as {@see suggestMeta()}, grounded differently.
     *
     * @param  list<CheckResult>  $findings  Typically {@see \Duxbo\Seo\Data\AnalysisReport::problems()}.
     * @return array{title?: string, description?: string}
     */
    public function suggestContentFixes(
        string $content,
        array $findings,
        ?SeoData $current = null,
        ?string $keyword = null,
        ?string $siteBrand = null,
        ?string $locale = null,
    ): array {
        $response = $this->manager->complete(
            $this->prompts->contentFix($content, $findings, $current, $keyword, $siteBrand, $locale),
            profile: self::PROFILE,
            purpose: 'content_fix',
        );

        /** @var array{title?: string, description?: string} $result */
        $result = $response->content;

        return $result;
    }

    /**
     * Suggest which of a shortlist of *real* URLs on this site should
     * replace a 404'd path — the caller builds the shortlist (e.g. from
     * existing titles/slugs), so the model picks among real candidates
     * instead of inventing one; the schema also constrains the answer to
     * exactly one of them.
     *
     * @param  list<array{url: string, title: string|null}>  $candidates  Must be non-empty.
     * @return array{targetUrl?: string, reasoning?: string}
     */
    public function suggestRedirectTarget(string $path, array $candidates, ?string $locale = null): array
    {
        $response = $this->manager->complete(
            $this->prompts->redirectTarget($path, $candidates, $locale),
            profile: self::PROFILE,
            purpose: 'redirect_target',
        );

        /** @var array{targetUrl?: string, reasoning?: string} $result */
        $result = $response->content;

        return $result;
    }

    /**
     * Suggest which of a set of topically-related pages should link to an
     * orphaned one, and with what anchor text. Propose-only: this package
     * has no write path into a model's own body content, so nothing can
     * apply this automatically the way a redirect or a meta suggestion can.
     *
     * @param  list<array{url: string, title: string|null}>  $candidateSources  Must be non-empty.
     * @return array{suggestions?: list<array{sourceUrl: string, anchorText: string}>}
     */
    public function suggestInternalLinkFixes(string $orphanUrl, string $orphanTitle, array $candidateSources, ?string $locale = null): array
    {
        $response = $this->manager->complete(
            $this->prompts->internalLinkFix($orphanUrl, $orphanTitle, $candidateSources, $locale),
            profile: self::PROFILE,
            purpose: 'internal_link',
        );

        /** @var array{suggestions?: list<array{sourceUrl: string, anchorText: string}>} $result */
        $result = $response->content;

        return $result;
    }

    /**
     * @return list<string>
     */
    public function suggestKeywords(string $content, ?string $locale = null): array
    {
        $response = $this->manager->complete(
            $this->prompts->keywords($content, $locale),
            profile: self::PROFILE,
            purpose: 'keywords',
        );

        $keywords = $response->get('keywords', []);

        return is_array($keywords)
            ? array_values(array_filter($keywords, 'is_string'))
            : [];
    }
}
