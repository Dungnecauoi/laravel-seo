<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai;

use Duxbo\Seo\Data\AiRequest;
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

    public function meta(string $content, ?string $keyword = null, ?string $locale = null): AiRequest
    {
        $locale ??= (string) $this->config->get('app.locale');
        $max = (int) $this->config->get('seo.limits.description_max', 158);

        return new AiRequest(
            prompt: $this->render('meta', [
                'content' => $this->trim($content),
                'keyword' => $keyword ?? '—',
                'max' => $max,
            ], $locale),
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
     * @param  array<string, mixed>  $replace
     */
    private function render(string $key, array $replace, string $locale): string
    {
        return (string) $this->translator->get("seo::prompts.{$key}", $replace, $locale);
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
