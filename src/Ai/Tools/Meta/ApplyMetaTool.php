<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Meta;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Exceptions\UnsafeCanonical;
use Duxbo\Seo\Http\Concerns\ResolvesExposedModel;
use Duxbo\Seo\Seo;
use Duxbo\Seo\Support\SameOriginUrls;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\MetaController::update()}
 * — same fields, same off-site canonical check ({@see SameOriginUrls}),
 * reached directly since this call never goes through Laravel's validator.
 */
final class ApplyMetaTool implements AiTool, AiToolPreviewable
{
    use ResolvesExposedModel;

    private const FIELDS = ['title', 'description', 'canonical', 'robots', 'focusKeyword', 'secondaryKeywords'];

    public function __construct(
        private readonly Seo $seo,
        private readonly SameOriginUrls $sameOrigin,
    ) {
    }

    public function name(): string
    {
        return 'seo.meta.apply';
    }

    public function description(): string
    {
        return 'Write SEO metadata for a record: title, description, canonical, robots, focus keyword, '
            .'secondary keywords, Open Graph and Twitter fields. Only the fields provided are changed.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string'],
                'id' => ['type' => 'string'],
                'locale' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'canonical' => ['type' => 'string'],
                'robots' => ['type' => 'array', 'items' => ['type' => 'string']],
                'focusKeyword' => ['type' => 'string'],
                'secondaryKeywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'og' => ['type' => 'object', 'description' => 'e.g. {"title": "...", "image": "..."}'],
                'twitter' => ['type' => 'object'],
            ],
            'required' => ['type', 'id'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Write;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        $this->resolveExposedModel((string) $input['type'], (string) $input['id']);
        $dotted = $this->dotted($input);

        return sprintf(
            'Would update SEO metadata for %s #%s: %s.',
            $input['type'],
            $input['id'],
            $dotted === [] ? '(nothing — no fields provided)' : implode(', ', array_keys($dotted)),
        );
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $model = $this->resolveExposedModel((string) $input['type'], (string) $input['id']);
        $locale = isset($input['locale']) ? (string) $input['locale'] : null;

        $this->seo->save($model, $this->dotted($input), $locale);

        return ['type' => (string) $input['type'], 'id' => (string) $input['id']];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function dotted(array $input): array
    {
        $this->assertCanonicalIsSafe($input['canonical'] ?? null);

        $dotted = [];

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $dotted[$field] = $input[$field];
            }
        }

        foreach (['og', 'twitter'] as $group) {
            if (is_array($input[$group] ?? null)) {
                foreach ($input[$group] as $key => $value) {
                    $dotted["{$group}.{$key}"] = $value;
                }
            }
        }

        return $dotted;
    }

    /**
     * @throws UnsafeCanonical
     */
    private function assertCanonicalIsSafe(mixed $canonical): void
    {
        if (! is_string($canonical) || $canonical === '' || $this->sameOrigin->isAllowed($canonical)) {
            return;
        }

        $host = parse_url($canonical, PHP_URL_HOST);

        throw UnsafeCanonical::hostNotAllowed(
            is_string($host) ? $host : $canonical,
            $this->sameOrigin->allowedHosts(),
        );
    }
}
