<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Meta;

use Duxbo\Seo\Ai\SeoAiManager;
use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Http\Concerns\ResolvesExposedModel;
use Duxbo\Seo\Seo;

/**
 * A suggestion, not a mutation — Read tier, no propose/confirm needed. Pairs
 * with {@see ApplyMetaTool} for a caller that wants to write it.
 */
final class SuggestMetaTool implements AiTool
{
    use ResolvesExposedModel;

    public function __construct(
        private readonly Seo $seo,
        private readonly SeoAiManager $ai,
    ) {
    }

    public function name(): string
    {
        return 'seo.meta.suggest';
    }

    public function description(): string
    {
        return 'Suggest a title and meta description for a record, grounded in its current stored '
            .'metadata and the site name, so the model improves what is there rather than guessing blind.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'description' => 'Morph alias, one of seo.api.models.'],
                'id' => ['type' => 'string'],
                'content' => ['type' => 'string', 'description' => 'The page content (HTML or plain text) to base the suggestion on.'],
                'keyword' => ['type' => 'string'],
                'locale' => ['type' => 'string'],
            ],
            'required' => ['type', 'id', 'content'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Read;
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $model = $this->resolveExposedModel((string) $input['type'], (string) $input['id']);
        $locale = isset($input['locale']) ? (string) $input['locale'] : $context->locale;
        $current = $this->seo->repository()->find($model, $locale);

        return $this->ai->suggestMeta(
            (string) $input['content'],
            isset($input['keyword']) ? (string) $input['keyword'] : null,
            $locale,
            $current,
            (string) config('seo.site_name', ''),
        );
    }
}
