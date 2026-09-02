<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Unit;

use Duxbo\Seo\Data\AnalysisReport;
use Duxbo\Seo\Data\CheckResult;
use PHPUnit\Framework\TestCase;

final class AnalysisReportTest extends TestCase
{
    public function test_a_skipped_check_neither_earns_nor_costs_anything(): void
    {
        $report = AnalysisReport::fromResults(
            [
                CheckResult::pass('a', 'ok'),
                CheckResult::skipped('b'),
            ],
            ['a' => 1, 'b' => 99],
        );

        // Were the skipped check counted against the total, this would be 1.
        $this->assertSame(100, $report->score);
    }

    public function test_a_warning_earns_half_its_weight(): void
    {
        $report = AnalysisReport::fromResults(
            [CheckResult::warning('a', 'nearly')],
            ['a' => 4],
        );

        $this->assertSame(50, $report->score);
    }

    public function test_weights_decide_how_much_a_failure_hurts(): void
    {
        $report = AnalysisReport::fromResults(
            [
                CheckResult::pass('heavy', 'ok'),
                CheckResult::fail('light', 'missing'),
            ],
            ['heavy' => 9, 'light' => 1],
        );

        $this->assertSame(90, $report->score);
    }

    public function test_an_empty_run_scores_zero_rather_than_dividing_by_zero(): void
    {
        $this->assertSame(0, AnalysisReport::fromResults([], [])->score);
    }

    public function test_problems_collects_failures_and_warnings_only(): void
    {
        $report = AnalysisReport::fromResults(
            [
                CheckResult::pass('a', 'ok'),
                CheckResult::warning('b', 'nearly'),
                CheckResult::fail('c', 'no'),
                CheckResult::skipped('d'),
            ],
            [],
        );

        $this->assertSame(['b', 'c'], array_map(
            static fn (CheckResult $r): string => $r->id,
            $report->problems(),
        ));
    }
}
