<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Console;

use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;

/**
 * The AI-facing twin of `php artisan seo:sitemap`. Tiered Write even for a
 * `list: true` call that writes nothing — the common case (no `list`) does
 * write files to disk, and one tool covering both keeps the risk tier
 * simple rather than depending on which argument was passed.
 */
final class RunSitemapCommandTool extends ConsoleCommandTool implements AiToolPreviewable
{
    public function name(): string
    {
        return 'seo.console.sitemap';
    }

    public function description(): string
    {
        return 'Run php artisan seo:sitemap: write the sitemap index and its source files to disk, '
            .'or pass list=true to only report the registered sources without writing anything.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'Directory to write into. Defaults to the public path.'],
                'list' => ['type' => 'boolean', 'description' => 'Show registered sources without writing anything.'],
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
        return 'seo:sitemap';
    }

    protected function arguments(array $input): array
    {
        $arguments = [];

        if (isset($input['path'])) {
            $arguments['--path'] = (string) $input['path'];
        }

        if (($input['list'] ?? false) === true) {
            $arguments['--list'] = true;
        }

        return $arguments;
    }
}
