<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\PageSpeed;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Exceptions\PageSpeedFetchFailed;
use Duxbo\Seo\PageSpeed\PageSpeedClient;

/**
 * The AI-facing twin of `php artisan seo:pagespeed`. Tiered Write, not Read,
 * because — like {@see \Duxbo\Seo\Ai\Tools\Console\RunAuditCommandTool} —
 * it makes a real outbound call and writes a new history row per URL, even
 * though nothing already stored is ever overwritten destructively.
 */
final class CheckUrlsTool implements AiTool, AiToolPreviewable
{
    /**
     * PageSpeed Insights has no batch endpoint — check() makes one
     * outbound request per URL, synchronously, inside this one tool call,
     * and each real PSI run typically takes several seconds. A cap keeps
     * one AI-authored URL list from turning a single tool call into a
     * multi-minute synchronous request.
     */
    private const MAX_URLS = 25;

    public function __construct(private readonly PageSpeedClient $client)
    {
    }

    public function name(): string
    {
        return 'seo.pagespeed.check';
    }

    public function description(): string
    {
        return 'Score one or more URLs with PageSpeed Insights (performance score, Core Web Vitals) '
            .'and store the result for trend tracking.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'urls' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => self::MAX_URLS],
                'strategy' => ['type' => 'string', 'enum' => ['mobile', 'desktop'], 'description' => 'Defaults to mobile.'],
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
            'Would score %d URL(s) with PageSpeed Insights (%s): %s',
            count($urls),
            $this->strategy($input),
            implode(', ', array_slice($urls, 0, 5)).(count($urls) > 5 ? ', …' : ''),
        );
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $strategy = $this->strategy($input);
        $urls = $this->urls($input);

        if (count($urls) > self::MAX_URLS) {
            throw new \InvalidArgumentException(sprintf(
                'seo.pagespeed.check accepts at most %d URLs per call (got %d) — split this into smaller batches.',
                self::MAX_URLS,
                count($urls),
            ));
        }

        $results = [];

        foreach ($urls as $url) {
            try {
                $results[] = $this->client->check($url, $strategy);
            } catch (PageSpeedFetchFailed $e) {
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

    /**
     * @param  array<string, mixed>  $input
     */
    private function strategy(array $input): string
    {
        $strategy = $input['strategy'] ?? 'mobile';

        return in_array($strategy, ['mobile', 'desktop'], true) ? $strategy : 'mobile';
    }
}
