<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Console;

use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;

/**
 * The AI-facing twin of `php artisan seo:audit`.
 */
final class RunAuditCommandTool extends ConsoleCommandTool implements AiToolPreviewable
{
    public function name(): string
    {
        return 'seo.console.audit';
    }

    public function description(): string
    {
        return 'Run php artisan seo:audit: score every record of a model and store the result as a new audit batch.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'model' => ['type' => 'string', 'description' => 'Fully-qualified model class, e.g. App\\Models\\Post.'],
                'content' => ['type' => 'string', 'description' => 'Attribute holding page content. Defaults to "body".'],
                'locale' => ['type' => 'string'],
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
        return 'seo:audit';
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
