<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Exceptions\PageSpeedFetchFailed;
use Duxbo\Seo\PageSpeed\PageSpeedClient;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class PageSpeedTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        config([
            'seo.pagespeed.enabled' => true,
            'seo.pagespeed.api_key' => 'test-api-key',
        ]);
    }

    private function fakePsi(): void
    {
        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::response([
                'lighthouseResult' => [
                    'categories' => ['performance' => ['score' => 0.94]],
                    'audits' => [
                        'largest-contentful-paint' => ['numericValue' => 1800.0],
                        'cumulative-layout-shift' => ['numericValue' => 0.02],
                        'total-blocking-time' => ['numericValue' => 120.0],
                        'first-contentful-paint' => ['numericValue' => 900.0],
                        'speed-index' => ['numericValue' => 1600.0],
                    ],
                ],
                'loadingExperience' => [
                    'overall_category' => 'FAST',
                    'metrics' => [],
                ],
            ], 200),
        ]);
    }

    public function test_the_command_reports_failure_when_disabled(): void
    {
        Http::fake();

        $this->artisan('seo:pagespeed', ['urls' => ['https://vidu.vn/']])
            ->expectsOutputToContain('seo.pagespeed.enabled is false')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_enabled_without_an_api_key_fails_loudly(): void
    {
        config(['seo.pagespeed.enabled' => true]);

        $this->expectException(PageSpeedFetchFailed::class);
        $this->expectExceptionMessage('api_key');

        $this->client()->check('https://vidu.vn/');
    }

    public function test_a_successful_check_parses_lab_metrics_and_field_data(): void
    {
        $this->configure();
        $this->fakePsi();

        $result = $this->client()->check('https://vidu.vn/', 'mobile');

        $this->assertSame(94, $result['performanceScore']);
        $this->assertSame(1800.0, $result['lcpMs']);
        $this->assertSame(0.02, $result['clsScore']);
        $this->assertTrue($result['fieldDataAvailable']);
        $this->assertSame('FAST', $result['cwvCategory']);

        Http::assertSent(function ($request): bool {
            $this->assertSame('test-api-key', $request->data()['key']);
            $this->assertSame('mobile', $request->data()['strategy']);

            return true;
        });
    }

    public function test_missing_field_data_is_reported_as_unavailable_not_an_error(): void
    {
        $this->configure();

        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::response([
                'lighthouseResult' => [
                    'categories' => ['performance' => ['score' => 0.5]],
                    'audits' => [],
                ],
                // No `loadingExperience` key at all — a low-traffic URL Google
                // has not collected enough real-user data for yet.
            ], 200),
        ]);

        $result = $this->client()->check('https://vidu.vn/it-di');

        $this->assertFalse($result['fieldDataAvailable']);
        $this->assertNull($result['cwvCategory']);
    }

    public function test_a_200_response_carrying_an_error_object_throws(): void
    {
        $this->configure();

        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::response([
                'error' => ['code' => 500, 'message' => 'Lighthouse returned error: FAILED_DOCUMENT_REQUEST.'],
            ], 200),
        ]);

        $this->expectException(PageSpeedFetchFailed::class);
        $this->expectExceptionMessage('FAILED_DOCUMENT_REQUEST');

        $this->client()->check('https://vidu.vn/khong-ton-tai');
    }

    public function test_a_non_2xx_response_throws(): void
    {
        $this->configure();

        Http::fake(['www.googleapis.com/pagespeedonline/*' => Http::response('Forbidden', 403)]);

        $this->expectException(PageSpeedFetchFailed::class);
        $this->expectExceptionMessage('HTTP 403');

        $this->client()->check('https://vidu.vn/');
    }

    public function test_checking_stores_one_row_for_the_day(): void
    {
        $this->configure();
        $this->fakePsi();

        $this->client()->check('https://vidu.vn/', 'mobile');

        $this->assertSame(1, DB::table('seo_pagespeed_stats')->count());

        $row = DB::table('seo_pagespeed_stats')->first();
        $this->assertSame('https://vidu.vn/', $row->url);
        $this->assertSame('mobile', $row->strategy);
        $this->assertSame(94, $row->performance_score);
        $this->assertSame(1, (int) $row->field_data_available);
    }

    public function test_re_checking_the_same_url_strategy_and_day_updates_rather_than_duplicates(): void
    {
        $this->configure();

        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::sequence()
                ->push(['lighthouseResult' => ['categories' => ['performance' => ['score' => 0.5]], 'audits' => []]])
                ->push(['lighthouseResult' => ['categories' => ['performance' => ['score' => 0.8]], 'audits' => []]]),
        ]);

        $this->client()->check('https://vidu.vn/', 'mobile');
        $this->client()->check('https://vidu.vn/', 'mobile');

        $this->assertSame(1, DB::table('seo_pagespeed_stats')->count());
        $this->assertSame(80, DB::table('seo_pagespeed_stats')->value('performance_score'));
    }

    public function test_mobile_and_desktop_are_stored_as_separate_rows(): void
    {
        $this->configure();
        $this->fakePsi();

        $this->client()->check('https://vidu.vn/', 'mobile');
        $this->client()->check('https://vidu.vn/', 'desktop');

        $this->assertSame(2, DB::table('seo_pagespeed_stats')->count());
    }

    public function test_fetch_does_not_store_a_row(): void
    {
        $this->configure();
        $this->fakePsi();

        $this->client()->fetch('https://vidu.vn/');

        $this->assertSame(0, DB::table('seo_pagespeed_stats')->count());
    }

    public function test_the_command_checks_each_url_and_reports_its_score(): void
    {
        $this->configure();
        $this->fakePsi();

        $this->artisan('seo:pagespeed', ['urls' => ['https://vidu.vn/a']])
            ->expectsOutputToContain('performance 94/100')
            ->assertSuccessful();
    }

    public function test_the_command_reports_a_per_url_failure_without_aborting_the_rest(): void
    {
        $this->configure();

        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::sequence()
                ->push('Forbidden', 403)
                ->push([
                    'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.7]], 'audits' => []],
                ]),
        ]);

        $this->artisan('seo:pagespeed', ['urls' => ['https://vidu.vn/loi', 'https://vidu.vn/on']])
            ->expectsOutputToContain('HTTP 403')
            ->expectsOutputToContain('performance 70/100')
            ->assertFailed();

        $this->assertSame(1, DB::table('seo_pagespeed_stats')->count());
    }

    private function client(): PageSpeedClient
    {
        return $this->app->make(PageSpeedClient::class);
    }
}
