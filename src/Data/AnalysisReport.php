<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use Duxbo\Seo\Enums\CheckStatus;

/**
 * Aggregate outcome of a full analysis run.
 */
final class AnalysisReport
{
    /**
     * @param  int  $score  0-100.
     * @param  list<CheckResult>  $results
     */
    public function __construct(
        public readonly int $score,
        public readonly array $results,
        public readonly ?string $locale = null,
    ) {
    }

    /**
     * Weighted score, with skipped checks excluded from the denominator so a
     * page is never punished for a check that could not run.
     *
     * @param  list<CheckResult>  $results
     * @param  array<string, int>  $weights  Check id => weight.
     */
    public static function fromResults(array $results, array $weights, ?string $locale = null): self
    {
        $earned = 0.0;
        $possible = 0.0;

        foreach ($results as $result) {
            if (! $result->status->counts()) {
                continue;
            }

            $weight = $weights[$result->id] ?? 1;
            $possible += $weight;
            $earned += $weight * $result->status->multiplier();
        }

        $score = $possible > 0.0 ? (int) round($earned / $possible * 100) : 0;

        return new self($score, $results, $locale);
    }

    /**
     * @return list<CheckResult>
     */
    public function withStatus(CheckStatus $status): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (CheckResult $result): bool => $result->status === $status,
        ));
    }

    /**
     * @return list<CheckResult>
     */
    public function problems(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (CheckResult $r): bool => $r->status === CheckStatus::Fail || $r->status === CheckStatus::Warning,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'locale' => $this->locale,
            'results' => array_map(static fn (CheckResult $r): array => $r->toArray(), $this->results),
        ];
    }
}
