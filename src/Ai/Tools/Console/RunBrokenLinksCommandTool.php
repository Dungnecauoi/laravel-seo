<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Console;

use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;

/**
 * The AI-facing twin of `php artisan seo:broken-links`.
 */
final class RunBrokenLinksCommandTool extends ConsoleCommandTool implements AiToolPreviewable
{
    public function name(): string
    {
        return 'seo.console.broken_links';
    }

    public function description(): string
    {
        return "Run php artisan seo:broken-links: crawl a model's content for external links and check "
            .'which are broken. Makes a real outbound request per distinct URL found.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'model' => ['type' => 'string', 'description' => 'Fully-qualified model class, e.g. App\\Models\\Post.'],
                'content' => ['type' => 'string', 'description' => 'Attribute holding page content. Defaults to "body".'],
            ],
            'required' => ['model'],
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
        return 'seo:broken-links';
    }

    protected function arguments(array $input): array
    {
        $arguments = ['model' => (string) $input['model']];

        if (isset($input['content'])) {
            $arguments['--content'] = (string) $input['content'];
        }

        return $arguments;
    }
}
