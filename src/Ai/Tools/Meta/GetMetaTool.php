<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Meta;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Http\Concerns\ResolvesExposedModel;
use Duxbo\Seo\Seo;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\MetaController::show()}
 * — same allowlist, same data, so an AI agent sees exactly what the REST API
 * and the panel already show a human.
 */
final class GetMetaTool implements AiTool
{
    use ResolvesExposedModel;

    public function __construct(private readonly Seo $seo)
    {
    }

    public function name(): string
    {
        return 'seo.meta.get';
    }

    public function description(): string
    {
        return 'Get the stored and resolved SEO metadata for one record, plus which locales it has metadata in.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'description' => 'Morph alias, one of seo.api.models.'],
                'id' => ['type' => 'string', 'description' => 'The record\'s key.'],
                'locale' => ['type' => 'string', 'description' => 'Optional. Defaults to the app locale.'],
            ],
            'required' => ['type', 'id'],
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

        return [
            'stored' => $this->seo->repository()->find($model, $locale)?->toArray(),
            'resolved' => $this->seo->for($model, $locale)->toArray(),
            'locales' => $this->seo->repository()->locales($model),
        ];
    }
}
