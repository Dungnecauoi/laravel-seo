<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Exceptions\IndexNowSubmissionFailed;
use Duxbo\Seo\IndexNow\IndexNowSubmitter;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class IndexNowTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_while_disabled_is_a_silent_no_op(): void
    {
        // Installing a package must never start an outbound request on its
        // own — this is the same promise the AI manager's NullDriver makes.
        Http::fake();

        $this->assertFalse($this->submitter()->submit('/bai-viet-moi'));

        Http::assertNothingSent();
    }

    public function test_enabled_without_a_key_fails_loudly_rather_than_silently(): void
    {
        config(['seo.indexnow.enabled' => true]);

        $this->expectException(IndexNowSubmissionFailed::class);
        $this->expectExceptionMessage('no key is configured');

        $this->submitter()->submit('/bai-viet-moi');
    }

    public function test_a_successful_submission_posts_the_host_key_and_url_list(): void
    {
        config([
            'seo.indexnow.enabled' => true,
            'seo.indexnow.key' => 'test-key-123',
        ]);

        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $result = $this->submitter()->submit(['/bai-viet-1', 'http://localhost/bai-viet-2']);

        $this->assertTrue($result);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            $this->assertSame('localhost', $body['host']);
            $this->assertSame('test-key-123', $body['key']);
            $this->assertSame('http://localhost/test-key-123.txt', $body['keyLocation']);
            $this->assertSame(
                ['http://localhost/bai-viet-1', 'http://localhost/bai-viet-2'],
                $body['urlList'],
            );

            return true;
        });
    }

    public function test_duplicate_urls_are_submitted_once(): void
    {
        config([
            'seo.indexnow.enabled' => true,
            'seo.indexnow.key' => 'test-key-123',
        ]);

        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $this->submitter()->submit(['/x', '/x', 'http://localhost/x']);

        Http::assertSent(function ($request): bool {
            $this->assertCount(1, $request->data()['urlList']);

            return true;
        });
    }

    public function test_a_failed_http_response_throws_rather_than_reporting_success(): void
    {
        config([
            'seo.indexnow.enabled' => true,
            'seo.indexnow.key' => 'test-key-123',
        ]);

        Http::fake(['api.indexnow.org/*' => Http::response('Forbidden', 403)]);

        $this->expectException(IndexNowSubmissionFailed::class);
        $this->expectExceptionMessage('HTTP 403');

        $this->submitter()->submit('/x');
    }

    public function test_the_key_file_route_does_not_exist_by_default(): void
    {
        // Registered from config read at boot, not per-request — so the
        // positive case (route exists once enabled) lives in
        // IndexNowKeyRouteTest, which enables it before the app boots
        // rather than flipping config() after the fact.
        $this->get('/whatever-key.txt')->assertNotFound();
    }

    public function test_the_console_command_reports_failure_when_disabled(): void
    {
        $this->artisan('seo:indexnow', ['urls' => ['/x']])
            ->expectsOutputToContain('seo.indexnow.enabled is false')
            ->assertFailed();
    }

    public function test_the_console_command_submits_and_reports_success(): void
    {
        config([
            'seo.indexnow.enabled' => true,
            'seo.indexnow.key' => 'test-key-123',
        ]);

        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $this->artisan('seo:indexnow', ['urls' => ['/x', '/y']])
            ->expectsOutputToContain('2 URL(s) submitted')
            ->assertSuccessful();
    }

    public function test_a_successful_submission_is_logged_as_one_row(): void
    {
        config([
            'seo.indexnow.enabled' => true,
            'seo.indexnow.key' => 'test-key-123',
        ]);

        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $this->submitter()->submit(['/a', '/a', '/b']);

        $this->assertSame(1, DB::table('seo_indexnow_log')->count());

        $row = DB::table('seo_indexnow_log')->first();
        $this->assertSame(1, (int) $row->successful);
        $this->assertSame(200, $row->status_code);
        $this->assertSame(2, $row->url_count);
        $this->assertSame(['http://localhost/a', 'http://localhost/b'], json_decode($row->urls, true));
    }

    public function test_a_failed_submission_is_logged_with_its_status_and_body(): void
    {
        config([
            'seo.indexnow.enabled' => true,
            'seo.indexnow.key' => 'test-key-123',
        ]);

        Http::fake(['api.indexnow.org/*' => Http::response('Forbidden', 403)]);

        try {
            $this->submitter()->submit('/x');
        } catch (IndexNowSubmissionFailed) {
            // Expected — the log assertion below is the point of this test.
        }

        $row = DB::table('seo_indexnow_log')->first();
        $this->assertSame(0, (int) $row->successful);
        $this->assertSame(403, $row->status_code);
        $this->assertSame('Forbidden', $row->error);
    }

    public function test_logging_can_be_turned_off(): void
    {
        config([
            'seo.indexnow.enabled' => true,
            'seo.indexnow.key' => 'test-key-123',
            'seo.indexnow.log' => false,
        ]);

        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $this->submitter()->submit('/x');

        $this->assertSame(0, DB::table('seo_indexnow_log')->count());
    }

    private function submitter(): IndexNowSubmitter
    {
        return $this->app->make(IndexNowSubmitter::class);
    }
}
