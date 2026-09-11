<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Ai\Tools\GoogleIndexing\SubmitUrlsTool;
use Duxbo\Seo\Ai\Tools\PageSpeed\CheckUrlsTool;
use Duxbo\Seo\Ai\Tools\SearchConsole\InspectUrlsTool;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * None of these three tools have a batch endpoint on the provider side —
 * each makes one outbound request per URL, synchronously, inside a single
 * tool call. Without a cap, one AI-authored URL list can run for an
 * unreasonable time or (for Search Console's own tightly-quota'd URL
 * Inspection API, ~2,000 requests/day/site) exhaust a meaningful chunk of
 * a whole day's quota in one call. `Http::fake()` with no matching route
 * proves these tests never reach the network — the limit is checked
 * before any request goes out.
 */
final class AiToolBatchLimitsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.google_indexing.enabled', true);
        $app['config']->set('seo.pagespeed.enabled', true);
        $app['config']->set('seo.search_console.enabled', true);
    }

    public function test_google_indexing_submit_refuses_a_batch_over_the_limit(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        app(SubmitUrlsTool::class)->execute(
            ['urls' => $this->urls(101)],
            new AiToolContext(),
        );

        Http::assertNothingSent();
    }

    public function test_pagespeed_check_refuses_a_batch_over_the_limit(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        app(CheckUrlsTool::class)->execute(
            ['urls' => $this->urls(26)],
            new AiToolContext(),
        );

        Http::assertNothingSent();
    }

    public function test_search_console_inspect_refuses_a_batch_over_the_limit(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        app(InspectUrlsTool::class)->execute(
            ['urls' => $this->urls(51)],
            new AiToolContext(),
        );

        Http::assertNothingSent();
    }

    /**
     * @return list<string>
     */
    private function urls(int $count): array
    {
        return array_map(
            static fn (int $i): string => "https://vidu.vn/trang-{$i}",
            range(1, $count),
        );
    }
}
