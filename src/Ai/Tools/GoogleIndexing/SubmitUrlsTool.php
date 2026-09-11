<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\GoogleIndexing;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\GoogleIndexing\GoogleIndexingClient;

/**
 * The AI-facing twin of `php artisan seo:google-indexing`. Tiered
 * Destructive not because it deletes anything, but because it is a real
 * outbound call against Google's own rate-limited quota — `preview()`
 * deliberately does *not* call the real endpoint, it only describes what
 * would be sent. Mirrors {@see \Duxbo\Seo\Ai\Tools\IndexNow\SubmitUrlsTool}.
 */
final class SubmitUrlsTool implements AiTool, AiToolPreviewable
{
    /**
     * No batch endpoint exists — submit() makes one outbound request per
     * URL, synchronously, inside this one tool call. Nothing here enforces
     * Google's own daily quota (that's account-specific and this package
     * has no way to know it), but capping how many requests one call can
     * fire keeps a single AI-authored URL list from either running for an
     * unreasonable time or single-handedly exhausting a day's quota.
     */
    private const MAX_URLS = 100;

    public function __construct(private readonly GoogleIndexingClient $client)
    {
    }

    public function name(): string
    {
        return 'seo.google_indexing.submit';
    }

    public function description(): string
    {
        return "Submit one or more URLs to Google's Indexing API for prompt re-crawling. Google only "
            .'documents this for JobPosting/BroadcastEvent pages, but the endpoint accepts any URL.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'urls' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => self::MAX_URLS],
                'deleted' => ['type' => 'boolean', 'description' => 'True to notify removal instead of an update.'],
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
            'Would submit %d URL(s) to Google Indexing API as %s: %s',
            count($urls),
            $this->type($input),
            implode(', ', array_slice($urls, 0, 5)).(count($urls) > 5 ? ', …' : ''),
        );
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $urls = $this->urls($input);

        if (count($urls) > self::MAX_URLS) {
            throw new \InvalidArgumentException(sprintf(
                'seo.google_indexing.submit accepts at most %d URLs per call (got %d) — split this into smaller batches.',
                self::MAX_URLS,
                count($urls),
            ));
        }

        $results = $this->client->submit($urls, $this->type($input));

        return [
            'results' => $results,
            'successCount' => count(array_filter($results, static fn (array $r): bool => $r['successful'])),
            'count' => count($results),
        ];
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

    /**
     * @param  array<string, mixed>  $input
     */
    private function type(array $input): string
    {
        return ($input['deleted'] ?? false) === true
            ? GoogleIndexingClient::TYPE_URL_DELETED
            : GoogleIndexingClient::TYPE_URL_UPDATED;
    }
}
