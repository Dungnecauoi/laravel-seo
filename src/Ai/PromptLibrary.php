<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai;

use Duxbo\AiCore\Data\AiRequest;
use Duxbo\Seo\Data\CheckResult;
use Duxbo\Seo\Data\SeoData;
use Duxbo\Seo\Support\Text;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Translation\Translator;

/**
 * Builds the prompts, in the language the content is written in.
 *
 * An English prompt asking for a Vietnamese meta description reliably produces
 * stilted Vietnamese, so the instruction itself is translated rather than the
 * output alone. Every string here is publishable and overridable.
 */
final class PromptLibrary
{
    public function __construct(
        private readonly Config $config,
        private readonly Translator $translator,
    ) {
    }

    /**
     * @param  list<CheckResult>  $findings  Real findings from an {@see \Duxbo\Seo\Data\AnalysisReport},
     *                                       so a suggestion targets what is actually wrong rather than
     *                                       guessing blind. Empty by default — every existing caller of
     *                                       this method keeps working unchanged.
     */
    public function meta(
        string $content,
        ?string $keyword = null,
        ?string $locale = null,
        ?SeoData $current = null,
        ?string $siteBrand = null,
        array $findings = [],
    ): AiRequest {
        $locale ??= (string) $this->config->get('app.locale');
        $max = (int) $this->config->get('seo.limits.description_max', 158);

        return new AiRequest(
            prompt: $this->withContext($this->render('meta', [
                'content' => $this->trim($content),
                'keyword' => $keyword ?? '—',
                'max' => $max,
            ], $locale), $current, $siteBrand, $findings, $locale),
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Page title'],
                    'description' => ['type' => 'string', 'description' => "Meta description, at most {$max} characters"],
                ],
                'required' => ['title', 'description'],
                'additionalProperties' => false,
            ],
            system: $this->render('system', [], $locale),
            locale: $locale,
        );
    }

    public function keywords(string $content, ?string $locale = null): AiRequest
    {
        $locale ??= (string) $this->config->get('app.locale');

        return new AiRequest(
            prompt: $this->render('keywords', ['content' => $this->trim($content)], $locale),
            schema: [
                'type' => 'object',
                'properties' => [
                    'keywords' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Between three and eight search phrases',
                    ],
                ],
                'required' => ['keywords'],
                'additionalProperties' => false,
            ],
            system: $this->render('system', [], $locale),
            locale: $locale,
        );
    }

    /**
     * A title/description suggestion grounded in real audit findings rather
     * than raw content alone — the same `{title, description}` shape as
     * {@see meta()}, but the prompt names exactly which checks are failing so
     * the model fixes those instead of writing generic meta from scratch.
     *
     * @param  list<CheckResult>  $findings  Typically {@see \Duxbo\Seo\Data\AnalysisReport::problems()}.
     */
    public function contentFix(
        string $content,
        array $findings,
        ?SeoData $current = null,
        ?string $keyword = null,
        ?string $siteBrand = null,
        ?string $locale = null,
    ): AiRequest {
        $locale ??= (string) $this->config->get('app.locale');
        $max = (int) $this->config->get('seo.limits.description_max', 158);

        return new AiRequest(
            prompt: $this->withContext($this->render('content_fix', [
                'content' => $this->trim($content),
                'keyword' => $keyword ?? '—',
                'max' => $max,
            ], $locale), $current, $siteBrand, $findings, $locale),
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Page title'],
                    'description' => ['type' => 'string', 'description' => "Meta description, at most {$max} characters"],
                ],
                'required' => ['title', 'description'],
                'additionalProperties' => false,
            ],
            system: $this->render('system', [], $locale),
            locale: $locale,
        );
    }

    /**
     * Asks the model to pick the best replacement for a 404'd path from a
     * shortlist of *real* URLs this application already knows about — never
     * an open-ended "guess a URL," which a model will confidently hallucinate.
     *
     * @param  list<array{url: string, title: string|null}>  $candidates
     */
    public function redirectTarget(string $path, array $candidates, ?string $locale = null): AiRequest
    {
        $locale ??= (string) $this->config->get('app.locale');

        return new AiRequest(
            prompt: $this->render('redirect_target', [
                'path' => $path,
                'candidates' => $this->numberedList($candidates),
            ], $locale),
            schema: [
                'type' => 'object',
                'properties' => [
                    'targetUrl' => [
                        'type' => 'string',
                        // A plain string field the model could still hallucinate
                        // outside the list despite the prompt's own instruction
                        // not to — constraining it to exactly the candidate URLs
                        // is enforced by the same structured-output mechanism as
                        // the rest of the schema, not merely asked for in prose.
                        'enum' => array_column($candidates, 'url'),
                        'description' => 'One of the candidate URLs above, verbatim',
                    ],
                    'reasoning' => ['type' => 'string', 'description' => 'One sentence explaining the choice'],
                ],
                'required' => ['targetUrl', 'reasoning'],
                'additionalProperties' => false,
            ],
            system: $this->render('system', [], $locale),
            locale: $locale,
        );
    }

    /**
     * Asks the model which of a set of topically-related pages should link
     * to an orphaned one, and with what anchor text — a proposal only, since
     * this package has no write path into a model's own body content.
     *
     * @param  list<array{url: string, title: string|null}>  $candidateSources
     */
    public function internalLinkFix(string $orphanUrl, string $orphanTitle, array $candidateSources, ?string $locale = null): AiRequest
    {
        $locale ??= (string) $this->config->get('app.locale');

        return new AiRequest(
            prompt: $this->render('internal_link', [
                'orphan_url' => $orphanUrl,
                'orphan_title' => $orphanTitle,
                'candidates' => $this->numberedList($candidateSources),
            ], $locale),
            schema: [
                'type' => 'object',
                'properties' => [
                    'suggestions' => [
                        'type' => 'array',
                        'description' => 'One to three of the candidate pages that should link to the orphan',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'sourceUrl' => [
                                    'type' => 'string',
                                    'enum' => array_column($candidateSources, 'url'),
                                    'description' => 'One of the candidate URLs above, verbatim',
                                ],
                                'anchorText' => ['type' => 'string'],
                            ],
                            'required' => ['sourceUrl', 'anchorText'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['suggestions'],
                'additionalProperties' => false,
            ],
            system: $this->render('system', [], $locale),
            locale: $locale,
        );
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    private function render(string $key, array $replace, string $locale): string
    {
        return (string) $this->translator->get("seo::prompts.{$key}", $replace, $locale);
    }

    /**
     * @param  list<array{url: string, title: string|null}>  $items
     */
    private function numberedList(array $items): string
    {
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $lines[] = sprintf(
                '%d. %s%s',
                $index + 1,
                $item['url'],
                $item['title'] !== null && $item['title'] !== '' ? ' — '.$item['title'] : '',
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Appends whatever grounding context is available — a site name, the
     * metadata already stored, real audit findings — as a clearly separate
     * section, so a caller that supplies none of it (every existing call to
     * {@see meta()} before this existed) gets back exactly the same prompt
     * as always instead of an empty "Additional context:" heading.
     *
     * @param  list<CheckResult>  $findings
     */
    private function withContext(string $prompt, ?SeoData $current, ?string $siteBrand, array $findings, string $locale): string
    {
        $lines = [];

        if ($siteBrand !== null && $siteBrand !== '') {
            $lines[] = $this->render('context_site', ['brand' => $siteBrand], $locale);
        }

        if ($current?->title !== null || $current?->description !== null) {
            $lines[] = $this->render('context_current', [
                'title' => $current->title ?? '—',
                'description' => $current->description ?? '—',
            ], $locale);
        }

        foreach ($findings as $finding) {
            $lines[] = '- '.trim(
                $this->translator->get($finding->message, $finding->context, $locale)
                .($finding->hint !== null ? ' '.$this->translator->get($finding->hint, $finding->context, $locale) : ''),
            );
        }

        if ($lines === []) {
            return $prompt;
        }

        $limit = (int) $this->config->get('seo.ai.context_characters', 1000);
        $context = mb_substr(implode("\n", $lines), 0, $limit);

        return $prompt."\n\n".$this->render('context_heading', [], $locale)."\n".$context;
    }

    /**
     * Send the model plain text, and only as much as it needs.
     *
     * Markup is noise it pays for by the token, and the opening of an article
     * carries almost all of the signal a title needs.
     */
    private function trim(string $content): string
    {
        $plain = Text::plain($content);
        $limit = (int) $this->config->get('seo.ai.content_characters', 4000);

        return mb_substr($plain, 0, $limit);
    }
}
