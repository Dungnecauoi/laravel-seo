<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Console;

use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;

/**
 * The AI-facing twin of `php artisan seo:internal-links`.
 */
final class RunInternalLinksCommandTool extends ConsoleCommandTool implements AiToolPreviewable
{
    public function name(): string
    {
        return 'seo.console.internal_links';
    }

    public function description(): string
    {
        return "Run php artisan seo:internal-links: crawl a model's content for internal links and "
            .'store the graph seo.internal_links.list reads.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'model' => ['type' => 'string', 'description' => 'Fully-qualified model class, e.g. App\\Models\\Post.'],
                'content' => ['type' => 'string', 'description' => 'Attribute holding page content. Defaults to "body".'],
                'locale' => ['type' => 'string', 'description' => 'Crawl as this locale and store rows tagged with it, so re-crawling one language never deletes another\'s.'],
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
        return 'seo:internal-links';
    }

    protected function arguments(array $input): array
    {
        $arguments = ['model' => (string) $input['model']];

        if (isset($input['content'])) {
            $arguments['--content'] = (string) $input['content'];
        }

        if (isset($input['locale'])) {
            $arguments['--locale'] = (string) $input['locale'];
        }

        return $arguments;
    }
}
