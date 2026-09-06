<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Console;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Enums\AiToolRisk;

/**
 * The AI-facing twin of `php artisan seo:hreflang` — read-only, so it runs
 * immediately with no proposal step.
 */
final class RunHreflangAuditCommandTool extends ConsoleCommandTool implements AiTool
{
    public function name(): string
    {
        return 'seo.console.hreflang';
    }

    public function description(): string
    {
        return 'Run php artisan seo:hreflang: find records whose hreflang alternates resolve to '
            .'the same URL for two different locales.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'model' => ['type' => 'string', 'description' => 'Fully-qualified model class, e.g. App\\Models\\Post.'],
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
        return 'seo:hreflang';
    }

    protected function arguments(array $input): array
    {
        return ['model' => (string) $input['model']];
    }
}
