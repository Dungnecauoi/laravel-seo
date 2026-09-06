<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Console;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Enums\AiToolRisk;

/**
 * The AI-facing twin of `php artisan seo:duplicates` — read-only, so it
 * runs immediately with no proposal step.
 */
final class RunDuplicatesCommandTool extends ConsoleCommandTool implements AiTool
{
    public function name(): string
    {
        return 'seo.console.duplicates';
    }

    public function description(): string
    {
        return 'Run php artisan seo:duplicates: find records whose fully-resolved title or '
            .'description matches another record.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'model' => ['type' => 'string', 'description' => 'Fully-qualified model class, e.g. App\\Models\\Post.'],
                'locale' => ['type' => 'string'],
                'field' => ['type' => 'string', 'enum' => ['title', 'description', 'both'], 'description' => 'Defaults to "title".'],
            ],
            'required' => ['model'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Read;
    }

    protected function signature(): string
    {
        return 'seo:duplicates';
    }

    protected function arguments(array $input): array
    {
        $arguments = ['model' => (string) $input['model']];

        if (isset($input['locale'])) {
            $arguments['--locale'] = (string) $input['locale'];
        }

        if (isset($input['field'])) {
            $arguments['--field'] = (string) $input['field'];
        }

        return $arguments;
    }
}
