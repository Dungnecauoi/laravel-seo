<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class SearchConsoleSyncTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        config([
            'seo.search_console.enabled' => true,
            'seo.search_console.client_id' => 'test-client-id',
            'seo.search_console.client_secret' => 'test-client-secret',
            'seo.search_console.refresh_token' => 'test-refresh-token',
            'seo.search_console.site_url' => 'https://trangcuatoi.vn/',
        ]);
    }

    private function fakeGoogle(array $rows = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access-token']),
            'www.googleapis.com/*' => Http::response(['rows' => $rows]),
        ]);
    }

    public function test_reports_failure_when_disabled(): void
    {
        Http::fake();

        $this->artisan('seo:search-console:sync')
            ->expectsOutputToContain('seo.search_console.enabled is false')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_reports_the_specific_missing_config_key_rather_than_a_generic_error(): void
    {
        config(['seo.search_console.enabled' => true]);
        Http::fake();

        $this->artisan('seo:search-console:sync')
            ->expectsOutputToContain('seo.search_console.client_id')
            ->assertFailed();
    }

    public function test_refreshes_a_token_then_queries_search_analytics(): void
    {
        $this->configure();
        $this->fakeGoogle([
            ['keys' => ['https://trangcuatoi.vn/bai-viet-1', '2026-01-01'], 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'position' => 5.5],
        ]);

        $this->artisan('seo:search-console:sync')
            ->expectsOutputToContain('1 row(s) synced')
            ->assertSuccessful();

        Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'oauth2.googleapis.com')
            && $request['refresh_token'] === 'test-refresh-token'
            && $request['grant_type'] === 'refresh_token');

        Http::assertSent(function ($request): bool {
            if (! str_contains((string) $request->url(), 'searchAnalytics/query')) {
                return false;
            }

            $this->assertSame('Bearer test-access-token', $request->header('Authorization')[0]);
            $this->assertSame(['page', 'date'], $request->data()['dimensions']);

            return true;
        });

        $row = DB::table('seo_search_console_stats')->first();

        $this->assertSame('https://trangcuatoi.vn/bai-viet-1', $row->url);
        $this->assertSame('2026-01-01', $row->date);
        $this->assertSame(10, $row->clicks);
        $this->assertSame(100, $row->impressions);
    }

    public function test_re_syncing_the_same_url_and_date_updates_rather_than_duplicates(): void
    {
        $this->configure();

        // A sequence, not two separate Http::fake() calls — a fresh fake()
        // call within the same test does not reliably replace the previous
        // one's stub for an identical URL pattern.
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access-token']),
            'www.googleapis.com/*' => Http::sequence()
                ->push(['rows' => [
                    ['keys' => ['https://trangcuatoi.vn/x', '2026-01-01'], 'clicks' => 5, 'impressions' => 50, 'ctr' => 0.1, 'position' => 8.0],
                ]])
                ->push(['rows' => [
                    ['keys' => ['https://trangcuatoi.vn/x', '2026-01-01'], 'clicks' => 12, 'impressions' => 90, 'ctr' => 0.13, 'position' => 4.2],
                ]]),
        ]);

        $this->artisan('seo:search-console:sync')->assertSuccessful();
        $this->artisan('seo:search-console:sync')->assertSuccessful();

        $this->assertSame(1, DB::table('seo_search_console_stats')->count());

        $row = DB::table('seo_search_console_stats')->first();
        $this->assertSame(12, $row->clicks);
    }

    public function test_a_failed_query_after_a_successful_token_refresh_is_reported(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access-token']),
            'www.googleapis.com/*' => Http::response('Forbidden', 403),
        ]);

        $this->artisan('seo:search-console:sync')
            ->expectsOutputToContain('HTTP 403')
            ->assertFailed();
    }

    public function test_a_revoked_refresh_token_is_reported_distinctly_from_a_query_failure(): void
    {
        $this->configure();

        Http::fake(['oauth2.googleapis.com/*' => Http::response('invalid_grant', 400)]);

        $this->artisan('seo:search-console:sync')
            ->expectsOutputToContain('refresh a Google access token')
            ->assertFailed();
    }
}
