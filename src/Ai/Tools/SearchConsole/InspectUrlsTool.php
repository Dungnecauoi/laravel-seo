<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\SearchConsole;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Exceptions\SearchConsoleSyncFailed;
use Duxbo\Seo\SearchConsole\SearchConsoleClient;

/**
 * The AI-facing twin of `php artisan seo:search-console:inspect`. Tiered
 * Write, not Read, for the same reason {@see
 * \Duxbo\Seo\Ai\Tools\PageSpeed\CheckUrlsTool} is: a real outbound call
 * against Google's own tightly rate-limited quota (roughly 2,000
 * requests/day per site), plus a new history row per URL, even though
 * nothing already stored is ever overwritten destructively.
 */
final class InspectUrlsTool implements AiTool, AiToolPreviewable
{
    /**
     * URL Inspection has no batch endpoint and Google rate-limits it far
     * tighter than search analytics (roughly 2,000 requests/day/site, per
     * this class's own docblock) — a single AI-authored URL list large
     * enough could exhaust a meaningful fraction of a whole day's quota in
     * one tool call. This package has no way to know an account's actual
     * remaining quota, so it cannot enforce the real daily limit, but
     * capping one call's own batch size keeps any single call's damage
     * bounded.
     */
    private const MAX_URLS = 50;

    public function __construct(private readonly SearchConsoleClient $client)
    {
    }

    public function name(): string
    {
        return 'seo.search_console.inspect';
    }

    public function description(): string
    {
        return "Check whether Google has URL(s) indexed via Search Console's URL Inspection API, and why "
            .'not if not (robots.txt, canonical mismatch, mobile usability, rich results).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'urls' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => self::MAX_URLS],
            ],
            'required' => ['urls'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Write;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        $urls = $this->urls($input);

        return sprintf(
            'Would inspect %d URL(s) via Search Console: %s',
            count($urls),
            implode(', ', array_slice($urls, 0, 5)).(count($urls) > 5 ? ', …' : ''),
        );
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $urls = $this->urls($input);

        if (count($urls) > self::MAX_URLS) {
            throw new \InvalidArgumentException(sprintf(
                'seo.search_console.inspect accepts at most %d URLs per call (got %d) — split this into smaller batches.',
                self::MAX_URLS,
                count($urls),
            ));
        }

        $results = [];

        foreach ($urls as $url) {
            try {
                $results[] = ['url' => $url, ...$this->client->inspect($url)];
            } catch (SearchConsoleSyncFailed $e) {
                $results[] = ['url' => $url, 'error' => $e->getMessage()];
            }
        }

        return ['results' => $results];
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
