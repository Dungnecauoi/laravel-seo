<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\NotFound;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\NotFound\NotFoundLogger;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\NotFoundController::prune()}.
 * `preview()` counts the rows the same query would delete, without deleting
 * them — a real dry run, not a guess.
 */
final class PruneNotFoundTool implements AiTool, AiToolPreviewable
{
    public function __construct(
        private readonly NotFoundLogger $logger,
        private readonly Config $config,
    ) {
    }

    public function name(): string
    {
        return 'seo.not_found.prune';
    }

    public function description(): string
    {
        return 'Delete logged 404 paths not seen again in the given number of days (default 90).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'days' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Defaults to 90.'],
            ],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Destructive;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        $days = $this->days($input);

        $count = DB::table((string) $this->config->get('seo.not_found.table', 'seo_not_found'))
            ->where('last_seen_at', '<', Carbon::now()->subDays($days))
            ->count();

        return sprintf('Would permanently delete %d 404 log entr%s not seen in the last %d days.', $count, $count === 1 ? 'y' : 'ies', $days);
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        return ['deleted' => $this->logger->prune($this->days($input))];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function days(array $input): int
    {
        return max(1, (int) ($input['days'] ?? 90));
    }
}
