<?php

declare(strict_types=1);

namespace Duxbo\Seo\GoogleIndexing;

use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Exceptions\GoogleIndexingSubmissionFailed;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pushes a URL straight to Google's own Indexing API
 * (`indexing.googleapis.com`) instead of waiting for the next crawl.
 *
 * Google only *documents* this API for two structured-data types —
 * JobPosting and BroadcastEvent (livestream) — and does not promise faster
 * indexing for anything else, even though the endpoint accepts and returns
 * 200 for any URL regardless of content. `enabled = true` here is an
 * explicit, informed choice a project makes for itself; this class does not
 * gate submission by content type, the same way {@see
 * \Duxbo\Seo\IndexNow\IndexNowSubmitter} does not ask what a URL is about
 * either. Submitting URLs outside the documented scope in bulk is against
 * Google's own guidance and has been reported to get accounts rate-limited
 * or suspended — that risk belongs to whoever turns this on, not to this
 * package.
 *
 * Auth is a Google service account, not an OAuth consent screen: create one
 * in Google Cloud Console, download its JSON key, add its `client_email` as
 * an Owner on the Search Console property being submitted to (Settings →
 * Users and permissions), and put `client_email` / `private_key` from that
 * JSON into config. This class only ever signs its own short-lived JWT
 * (RS256, via the `openssl` extension PHP ships with) and exchanges it for
 * an access token — no OAuth consent flow runs here, mirroring the reasoning
 * behind {@see \Duxbo\Seo\SearchConsole\SearchConsoleClient} never running
 * one either.
 *
 * Unlike IndexNow's one-call batch, the Indexing API takes exactly one URL
 * per publish call, so a multi-URL submit() makes one HTTP call per URL and
 * reports each individually rather than collapsing them into a single
 * pass/fail — a partial failure on URL 8 of 10 should not hide the 7 that
 * went through.
 */
final class GoogleIndexingClient
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const PUBLISH_ENDPOINT = 'https://indexing.googleapis.com/v3/urlNotifications:publish';

    private const SCOPE = 'https://www.googleapis.com/auth/indexing';

    public const TYPE_URL_UPDATED = 'URL_UPDATED';

    public const TYPE_URL_DELETED = 'URL_DELETED';

    public function __construct(
        private readonly Http $http,
        private readonly Config $config,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function enabled(): bool
    {
        return $this->config->get('seo.google_indexing.enabled', false) === true;
    }

    /**
     * @param  string|list<string>  $urls  Absolute or site-relative.
     * @return list<array{url: string, type: string, successful: bool, statusCode: int|null, error: string|null}>
     */
    public function submit(string|array $urls, string $type = self::TYPE_URL_UPDATED): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $urlList = array_values(array_unique(array_map(
            fn (string $url): string => $this->urls->absolute($url),
            is_string($urls) ? [$urls] : $urls,
        )));

        if ($urlList === []) {
            return [];
        }

        $token = $this->accessToken();

        return array_map(
            fn (string $url): array => $this->publish($url, $type, $token),
            $urlList,
        );
    }

    /**
     * @return array{url: string, type: string, successful: bool, statusCode: int|null, error: string|null}
     */
    private function publish(string $url, string $type, string $token): array
    {
        try {
            $response = $this->http
                ->withToken($token)
                ->timeout(15)
                ->post(self::PUBLISH_ENDPOINT, ['url' => $url, 'type' => $type]);

            $successful = $response->successful();
            $status = $response->status();
            $error = $successful ? null : mb_substr((string) $response->body(), 0, 500);
        } catch (ConnectionException $e) {
            $successful = false;
            $status = null;
            $error = $e->getMessage();
        }

        $this->log($url, $type, $successful, $status, $error);

        return ['url' => $url, 'type' => $type, 'successful' => $successful, 'statusCode' => $status, 'error' => $error];
    }

    private function log(string $url, string $type, bool $successful, ?int $status, ?string $error): void
    {
        if ($this->config->get('seo.google_indexing.log', true) !== true) {
            return;
        }

        DB::table($this->logTable())->insert([
            'url' => $url,
            'type' => $type,
            'successful' => $successful,
            'status_code' => $status,
            'error' => $error,
            'created_at' => Carbon::now(),
        ]);
    }

    private function logTable(): string
    {
        return (string) $this->config->get('seo.google_indexing.log_table', 'seo_google_indexing_log');
    }

    private function accessToken(): string
    {
        $jwt = $this->signedJwt();

        $response = $this->http->asForm()->timeout(15)->post(self::TOKEN_ENDPOINT, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->failed()) {
            throw GoogleIndexingSubmissionFailed::tokenRequestFailed($response->status(), (string) $response->body());
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw GoogleIndexingSubmissionFailed::tokenRequestFailed($response->status(), 'Response carried no access_token.');
        }

        return $token;
    }

    private function signedJwt(): string
    {
        $email = $this->requireConfig('client_email');
        $privateKey = $this->requireConfig('private_key');

        $now = time();

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $email,
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_ENDPOINT,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $signingInput = "{$header}.{$claims}";

        // A private key pasted into a single-line .env value carries its
        // newlines escaped as the two characters `\` and `n` — openssl_sign
        // needs the PEM's real line breaks to parse it.
        $key = str_contains($privateKey, "\\n") ? str_replace("\\n", "\n", $privateKey) : $privateKey;

        $signature = '';

        if (openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256) !== true) {
            throw GoogleIndexingSubmissionFailed::invalidPrivateKey();
        }

        return "{$signingInput}.".$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function requireConfig(string $key): string
    {
        $value = $this->config->get("seo.google_indexing.{$key}");

        if (! is_string($value) || $value === '') {
            throw GoogleIndexingSubmissionFailed::notConfigured("seo.google_indexing.{$key}");
        }

        return $value;
    }
}
