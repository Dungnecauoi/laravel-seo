<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\IndexNow;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\IndexNow\IndexNowSubmitter;

/**
 * The AI-facing twin of `php artisan seo:indexnow`. Tiered Destructive not
 * because it deletes anything, but because it is a real outbound network
 * call against a rate-limited third-party quota — `preview()` deliberately
 * does *not* call the real endpoint (that would be the very side effect
 * being gated), it only describes what would be sent.
 */
final class SubmitUrlsTool implements AiTool, AiToolPreviewable
{
    public function __construct(private readonly IndexNowSubmitter $submitter)
    {
    }

    public function name(): string
    {
        return 'seo.indexnow.submit';
    }

    public function description(): string
    {
        return 'Submit one or more URLs to IndexNow (Bing, Yandex and other participating search engines) for prompt re-crawling.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'urls' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],
            ],
            'required' => ['urls'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Destructive;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        $urls = $this->urls($input);

        return sprintf(
            'Would submit %d URL(s) to IndexNow: %s',
            count($urls),
            implode(', ', array_slice($urls, 0, 5)).(count($urls) > 5 ? ', …' : ''),
        );
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $urls = $this->urls($input);

        return ['submitted' => $this->submitter->submit($urls), 'count' => count($urls)];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function urls(array $input): array
    {
        /** @var list<mixed> $raw */
        $raw = $input['urls'] ?? [];

        return array_values(array_map(static fn (mixed $url): string => (string) $url, $raw));
    }
}
