<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Console;

use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;

/**
 * The AI-facing twin of `php artisan seo:search-console:sync`.
 */
final class RunSearchConsoleSyncCommandTool extends ConsoleCommandTool implements AiToolPreviewable
{
    public function name(): string
    {
        return 'seo.console.search_console_sync';
    }

    public function description(): string
    {
        return "Run php artisan seo:search-console:sync: pull the site's Search Console clicks, "
            .'impressions and position for recent days.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'days' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Defaults to 7.'],
            ],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Write;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        return sprintf('Would run `php artisan %s`.', $this->commandLine($this->arguments($input)));
    }

    protected function signature(): string
    {
        return 'seo:search-console:sync';
    }

    protected function arguments(array $input): array
    {
        $arguments = [];

        if (isset($input['days'])) {
            $arguments['--days'] = (string) (int) $input['days'];
        }

        return $arguments;
    }
}
