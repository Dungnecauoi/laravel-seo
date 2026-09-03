<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai;

use Duxbo\Seo\Data\AiResponse;
use Duxbo\Seo\Exceptions\AiRequestFailed;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records every call, and stops once the day's token budget is spent.
 *
 * A loop over a few thousand records is an ordinary thing to write and an
 * expensive thing to run. The cap exists so that mistake costs one day's budget
 * rather than an invoice nobody saw coming.
 */
final class AiBudget
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @throws AiRequestFailed
     */
    public function assertWithinBudget(): void
    {
        $limit = (int) $this->config->get('seo.ai.daily_token_budget', 0);

        if ($limit <= 0) {
            return;
        }

        $used = $this->tokensUsedToday();

        if ($used >= $limit) {
            throw AiRequestFailed::budgetExceeded($used, $limit);
        }
    }

    public function tokensUsedToday(): int
    {
        return (int) DB::table($this->table())
            ->where('created_at', '>=', Carbon::today())
            ->sum(DB::raw('input_tokens + output_tokens'));
    }

    public function record(AiResponse $response, ?string $purpose = null): void
    {
        if ($this->config->get('seo.ai.log', true) !== true) {
            return;
        }

        DB::table($this->table())->insert([
            'driver' => $response->driver,
            'model' => $response->model,
            'purpose' => $purpose,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'cost' => $this->cost($response),
            'currency' => $this->config->get('seo.ai.pricing.currency', 'USD'),
            'from_cache' => $response->fromCache,
            'created_at' => Carbon::now(),
        ]);
    }

    public function recordFailure(string $driver, string $error, ?string $purpose = null): void
    {
        if ($this->config->get('seo.ai.log', true) !== true) {
            return;
        }

        DB::table($this->table())->insert([
            'driver' => $driver,
            'purpose' => $purpose,
            'error' => mb_substr($error, 0, 1000),
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Prices are configuration, not constants.
     *
     * Hard-coding them would make the package wrong within months and quietly
     * wrong at that — a cost column that drifts is worse than an empty one.
     */
    private function cost(AiResponse $response): ?float
    {
        $model = $response->model;

        if ($model === null) {
            return null;
        }

        /** @var array<string, array{input?: float, output?: float}> $pricing */
        $pricing = $this->config->get('seo.ai.pricing.models', []);

        $rates = $pricing[$model] ?? null;

        if ($rates === null) {
            return null;
        }

        // Rates are per million tokens, which is how every provider quotes them.
        return round(
            $response->inputTokens / 1_000_000 * (float) ($rates['input'] ?? 0)
            + $response->outputTokens / 1_000_000 * (float) ($rates['output'] ?? 0),
            6,
        );
    }

    private function table(): string
    {
        /** @var string $table */
        $table = $this->config->get('seo.ai.table', 'seo_ai_log');

        return $table;
    }
}
