<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Exceptions\SearchConsoleSyncFailed;
use Duxbo\Seo\SearchConsole\SearchConsoleClient;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class SearchConsoleInspectTest extends TestCase
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

    private function fakeGoogle(array $inspectionResult): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access-token']),
            'searchconsole.googleapis.com/*' => Http::response(['inspectionResult' => $inspectionResult]),
        ]);
    }

    public function test_the_command_reports_failure_when_disabled(): void
    {
        Http::fake();

        $this->artisan('seo:search-console:inspect', ['urls' => ['https://trangcuatoi.vn/x']])
            ->expectsOutputToContain('seo.search_console.enabled is false')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_a_successful_inspection_parses_index_status_mobile_and_rich_results(): void
    {
        $this->configure();
        $this->fakeGoogle([
            'inspectionResultLink' => 'https://search.google.com/...',
            'indexStatusResult' => [
                'verdict' => 'PASS',
                'coverageState' => 'Submitted and indexed',
                'robotsTxtState' => 'ALLOWED',
                'indexingState' => 'INDEXING_ALLOWED',
                'pageFetchState' => 'SUCCESSFUL',
                'lastCrawlTime' => '2026-01-01T00:00:00Z',
                'googleCanonical' => 'https://trangcuatoi.vn/bai-viet',
                'userCanonical' => 'https://trangcuatoi.vn/bai-viet',
                'sitemap' => ['https://trangcuatoi.vn/sitemap.xml'],
                'referringUrls' => ['https://trangcuatoi.vn/'],
            ],
            'mobileUsabilityResult' => ['verdict' => 'PASS', 'issues' => []],
            'richResultsResult' => ['verdict' => 'NEUTRAL'],
        ]);

        $client = $this->app->make(SearchConsoleClient::class);
        $result = $client->inspect('https://trangcuatoi.vn/bai-viet');

        $this->assertSame('PASS', $result['verdict']);
        $this->assertSame('Submitted and indexed', $result['coverageState']);
        $this->assertSame('ALLOWED', $result['robotsTxtState']);
        $this->assertSame(['https://trangcuatoi.vn/sitemap.xml'], $result['sitemaps']);
        $this->assertSame('PASS', $result['mobileUsabilityVerdict']);
        $this->assertSame([], $result['mobileUsabilityIssues']);

        Http::assertSent(function ($request): bool {
            if (! str_contains((string) $request->url(), 'urlInspection')) {
                return true;
            }

            $this->assertSame('Bearer test-access-token', $request->header('Authorization')[0]);
            $this->assertSame('https://trangcuatoi.vn/bai-viet', $request->data()['inspectionUrl']);
            $this->assertSame('https://trangcuatoi.vn/', $request->data()['siteUrl']);

            return true;
        });
    }

    public function test_mobile_usability_issues_are_reduced_to_their_issue_types(): void
    {
        $this->configure();
        $this->fakeGoogle([
            'indexStatusResult' => ['verdict' => 'FAIL', 'coverageState' => 'Crawled - currently not indexed'],
            'mobileUsabilityResult' => [
                'verdict' => 'FAIL',
                'issues' => [
                    ['issueType' => 'USES_INCOMPATIBLE_PLUGINS', 'severity' => 'ERROR'],
                    ['issueType' => 'TEXT_TOO_SMALL', 'severity' => 'WARNING'],
                ],
            ],
        ]);

        $client = $this->app->make(SearchConsoleClient::class);
        $result = $client->inspect('https://trangcuatoi.vn/di-dong');

        $this->assertSame(['USES_INCOMPATIBLE_PLUGINS', 'TEXT_TOO_SMALL'], $result['mobileUsabilityIssues']);
    }

    public function test_a_failed_inspection_request_throws(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access-token']),
            'searchconsole.googleapis.com/*' => Http::response('Forbidden', 403),
        ]);

        $this->expectException(SearchConsoleSyncFailed::class);
        $this->expectExceptionMessage('HTTP 403');

        $this->app->make(SearchConsoleClient::class)->inspect('https://trangcuatoi.vn/x');
    }

    public function test_inspecting_stores_one_row_for_the_day(): void
    {
        $this->configure();
        $this->fakeGoogle([
            'indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed'],
        ]);

        $this->app->make(SearchConsoleClient::class)->inspect('https://trangcuatoi.vn/bai-viet');

        $this->assertSame(1, DB::table('seo_url_inspections')->count());

        $row = DB::table('seo_url_inspections')->first();
        $this->assertSame('https://trangcuatoi.vn/bai-viet', $row->url);
        $this->assertSame('PASS', $row->verdict);
        $this->assertSame('Submitted and indexed', $row->coverage_state);
    }

    public function test_re_inspecting_the_same_url_and_day_updates_rather_than_duplicates(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access-token']),
            'searchconsole.googleapis.com/*' => Http::sequence()
                ->push(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'NEUTRAL']]])
                ->push(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS']]]),
        ]);

        $client = $this->app->make(SearchConsoleClient::class);
        $client->inspect('https://trangcuatoi.vn/x');
        $client->inspect('https://trangcuatoi.vn/x');

        $this->assertSame(1, DB::table('seo_url_inspections')->count());
        $this->assertSame('PASS', DB::table('seo_url_inspections')->value('verdict'));
    }

    public function test_inspect_url_does_not_store_a_row(): void
    {
        $this->configure();
        $this->fakeGoogle(['indexStatusResult' => ['verdict' => 'PASS']]);

        $this->app->make(SearchConsoleClient::class)->inspectUrl('https://trangcuatoi.vn/x');

        $this->assertSame(0, DB::table('seo_url_inspections')->count());
    }

    public function test_the_command_reports_each_urls_coverage_state(): void
    {
        $this->configure();
        $this->fakeGoogle([
            'indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed'],
        ]);

        $this->artisan('seo:search-console:inspect', ['urls' => ['https://trangcuatoi.vn/bai-viet']])
            ->expectsOutputToContain('Submitted and indexed (PASS)')
            ->assertSuccessful();
    }

    public function test_the_command_reports_a_per_url_failure_without_aborting_the_rest(): void
    {
        $this->configure();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access-token']),
            'searchconsole.googleapis.com/*' => Http::sequence()
                ->push('Forbidden', 403)
                ->push(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed']]]),
        ]);

        $this->artisan('seo:search-console:inspect', ['urls' => ['https://trangcuatoi.vn/loi', 'https://trangcuatoi.vn/on']])
            ->expectsOutputToContain('HTTP 403')
            ->expectsOutputToContain('Submitted and indexed (PASS)')
            ->assertFailed();

        $this->assertSame(1, DB::table('seo_url_inspections')->count());
    }
}
