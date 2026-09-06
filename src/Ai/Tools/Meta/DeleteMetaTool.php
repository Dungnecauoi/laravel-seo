<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Meta;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Http\Concerns\ResolvesExposedModel;
use Duxbo\Seo\Seo;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\MetaController::destroy()}.
 */
final class DeleteMetaTool implements AiTool, AiToolPreviewable
{
    use ResolvesExposedModel;

    public function __construct(private readonly Seo $seo)
    {
    }

    public function name(): string
    {
        return 'seo.meta.delete';
    }

    public function description(): string
    {
        return 'Delete the stored SEO metadata for one record and locale — the record itself is untouched, '
            .'only its metadata row. A null locale addresses the shared, locale-independent row.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string'],
                'id' => ['type' => 'string'],
                'locale' => ['type' => 'string'],
            ],
            'required' => ['type', 'id'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Destructive;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        $this->resolveExposedModel((string) $input['type'], (string) $input['id']);
        $locale = isset($input['locale']) ? (string) $input['locale'] : null;

        return sprintf(
            'Would permanently delete stored SEO metadata for %s #%s (%s).',
            $input['type'],
            $input['id'],
            $locale ?? 'shared, no locale',
        );
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $model = $this->resolveExposedModel((string) $input['type'], (string) $input['id']);
        $locale = isset($input['locale']) ? (string) $input['locale'] : null;

        $this->seo->forget($model, $locale);

        return ['type' => (string) $input['type'], 'id' => (string) $input['id'], 'deleted' => true];
    }
}
