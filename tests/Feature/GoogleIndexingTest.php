<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Exceptions\GoogleIndexingSubmissionFailed;
use Duxbo\Seo\GoogleIndexing\GoogleIndexingClient;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class GoogleIndexingTest extends TestCase
{
    use RefreshDatabase;

    // A throwaway RSA key generated with `openssl genrsa 2048` purely so
    // openssl_sign() has something real to sign in tests — it belongs to no
    // Google service account and is never used outside this file.
    private const TEST_PRIVATE_KEY = <<<'PEM'
        -----BEGIN PRIVATE KEY-----
        MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCm7rSdu7F7OXaR
        tvK1bGN21slULX2HlbFR7HkfEZvEENHf35E/ziORKM4hnK7n2USWj3yAqmBm8e7t
        YnxDh/naqyvUaRvBmtvsFezi1B2f2E9p7IxxQ7OfnqHDxYUIGdlVtGCus/2Wm8xo
        OIw6HNPHPQO6TyonBj1kXRFtxwkiUr22beLZaveSyDbC+Nkvr3UjyUimTzqpNDSI
        DM5DoD4/xpgusqS+3d51/CSkoEtQ4m+N4civIFPEGpakS3dvisnSx3t28QyXmPWz
        e25liA1DemUkG2Ec+dswV6IQLD3gvGXt4YC7D6Z6yqqGaBpPjn+DXWwcL0ypI+3Q
        wt4w36qrAgMBAAECggEAJ59GAKBxznlDsu91KWnKLAVsMQ5BGuMFfRf/Ycf4rG9/
        mT9EBxyDJavFTYYWn9JarM/u8c0+54BqQS8cppzKScRSOW8fVvNOWvmTSf2l8HBT
        3ST36tRyeKMa61BhNJyKEQUo4562RL7DJEOzyQFZpRNO5LRwoWWiQcWzYtaYzOvq
        ya6+m5/E6f/kcLsqRgViGgpp4bw4FqpnJwE8pzSFSVoXAlOPpcdhMl1n79VOxtbQ
        Yr4p/ToMoJX0FGk4taHRpT4jiJeCiZmQT1LFtxPzty6zGvrkQTJK9AsaZHLaujBA
        beraa4glA93uLwHK8TNRr91Sqh73h2RwKw6zJTz3BQKBgQDq+pmG37wV3jPQMYiB
        Kg97G0Tmic/NCnvK6mpxjKI+Gj725wlTOabwsiV9630qs1KBqyEJSj55dFDlO5y8
        FdrO0/uyIzps8W5yNN0Vi7la+XSDBnzEVvMf0eZucKqZ+FAdoGjx0Qdlka0Y/B5c
        XS+tIhaZA6Md1qZW9aImcmxC7wKBgQC13bsXur6dNt3GrRfrUbABBUZZXr6cPKbr
        6suvAHRLigT+uaz9I4H5WMp56ALzpuiOSPFutMoft7rvt9Yz0+PQ6cijeL3J4nrk
        YUhAJqjn541+fTP7eZ1e1TAuKI7T68wLgcaG5KhsKPIWd04Ygsctw4jCiFsMJjNf
        4Xlm+zNkBQKBgCqFDFD2nV9LpQ6QWAYfaUu6hH4/A8YzlnECeB5x903LjAc7iVlw
        /j9hzRz7Btw6NLzYDZNTxvhNTvIcFmGGhuCURWBXtZPPIFA0NqlWbkUDDM1E2EDj
        Bv2ECvGDG6ve3ghuZW8UhwUfFjaGMKxABIeMupAXs2WL2O+1ZREV9XcrAoGAIFTq
        BP7zEjoF1WNCZFhiZNwONKcuVdJGjpxwV41KH3/LAYn64gnk+nI5lNCVbcGXiGwZ
        V+gWIutj9WgGUbJyxto5DC7T9scnt+A/mwAEeS3mLr2Nd0CYpJpb9WjKc4xw+v64
        T5TpCEmDOFE/dgYR6OXhM0xQe2lzKrGIBrHT4g0CgYEAielOnruXgperSKzOtMY1
        PI/M24k3O1MdfTz9QFe1On5pg8iS1n19LxgQbTu9gUJ8xatl7wbT5hYd+veu4M+M
        ORPT0QYMTdUEumNQORbRrHOIxNSt0hnjYvrMZIBKJ9Ae+3NUKNL+Bc8JE/i2npvX
        0PEOeyZJpuh+YRrL6+jKjm4=
        -----END PRIVATE KEY-----
        PEM;

    public function test_submitting_while_disabled_is_a_silent_no_op(): void
    {
        Http::fake();

        $this->assertSame([], $this->client()->submit('/bai-viet-moi'));

        Http::assertNothingSent();
    }

    public function test_enabled_without_credentials_fails_loudly_rather_than_silently(): void
    {
        config(['seo.google_indexing.enabled' => true]);

        $this->expectException(GoogleIndexingSubmissionFailed::class);
        $this->expectExceptionMessage('client_email');

        $this->client()->submit('/bai-viet-moi');
    }

    public function test_a_successful_submission_publishes_each_url_and_logs_it(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'indexing.googleapis.com/*' => Http::response(['urlNotificationMetadata' => []], 200),
        ]);

        $results = $this->client()->submit(['/a', 'http://localhost/b']);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['successful']);
        $this->assertTrue($results[1]['successful']);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'indexing.googleapis.com')) {
                return true;
            }

            $this->assertSame('URL_UPDATED', $request->data()['type']);
            $this->assertSame('Bearer fake-token', $request->header('Authorization')[0]);

            return true;
        });

        $this->assertSame(2, DB::table('seo_google_indexing_log')->count());
    }

    public function test_deleted_type_is_sent_when_requested(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'indexing.googleapis.com/*' => Http::response([], 200),
        ]);

        $this->client()->submit('/removed', GoogleIndexingClient::TYPE_URL_DELETED);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'indexing.googleapis.com')) {
                return true;
            }

            $this->assertSame('URL_DELETED', $request->data()['type']);

            return true;
        });
    }

    public function test_one_failing_url_does_not_hide_the_others_that_succeeded(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'indexing.googleapis.com/*' => Http::sequence()
                ->push([], 200)
                ->push('Forbidden', 403),
        ]);

        $results = $this->client()->submit(['/ok', '/blocked']);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['successful']);
        $this->assertFalse($results[1]['successful']);
        $this->assertSame(403, $results[1]['statusCode']);

        $this->assertSame(1, DB::table('seo_google_indexing_log')->where('successful', true)->count());
        $this->assertSame(1, DB::table('seo_google_indexing_log')->where('successful', false)->count());
    }

    public function test_a_failed_token_request_throws(): void
    {
        $this->configureCredentials();

        Http::fake(['oauth2.googleapis.com/*' => Http::response('invalid_grant', 400)]);

        $this->expectException(GoogleIndexingSubmissionFailed::class);
        $this->expectExceptionMessage('HTTP 400');

        $this->client()->submit('/x');
    }

    public function test_the_console_command_reports_failure_when_disabled(): void
    {
        $this->artisan('seo:google-indexing', ['urls' => ['/x']])
            ->expectsOutputToContain('seo.google_indexing.enabled is false')
            ->assertFailed();
    }

    public function test_the_console_command_submits_and_reports_success(): void
    {
        $this->configureCredentials();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'indexing.googleapis.com/*' => Http::response([], 200),
        ]);

        $this->artisan('seo:google-indexing', ['urls' => ['/x', '/y']])
            ->expectsOutputToContain('2/2 URL(s) submitted')
            ->assertSuccessful();
    }

    public function test_logging_can_be_turned_off(): void
    {
        $this->configureCredentials();
        config(['seo.google_indexing.log' => false]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'indexing.googleapis.com/*' => Http::response([], 200),
        ]);

        $this->client()->submit('/x');

        $this->assertSame(0, DB::table('seo_google_indexing_log')->count());
    }

    private function configureCredentials(): void
    {
        config([
            'seo.google_indexing.enabled' => true,
            'seo.google_indexing.client_email' => 'test@example-project.iam.gserviceaccount.com',
            'seo.google_indexing.private_key' => self::TEST_PRIVATE_KEY,
        ]);
    }

    private function client(): GoogleIndexingClient
    {
        return $this->app->make(GoogleIndexingClient::class);
    }
}
