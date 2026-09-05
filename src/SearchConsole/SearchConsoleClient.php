<?php

declare(strict_types=1);

namespace Duxbo\Seo\SearchConsole;

use Duxbo\Seo\Exceptions\SearchConsoleSyncFailed;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;

/**
 * Reads page performance from the Search Console API.
 *
 * Talks plain REST through Laravel's own HTTP client, the same reasoning
 * behind the AI drivers not depending on `google/apiclient`: an SDK is one
 * more library whose abandonment or next breaking major becomes this
 * package's problem, for a feature most projects using this package will
 * never turn on.
 *
 * The OAuth consent screen itself is never run by this class or by
 * anything in this package — only a refresh token obtained once, outside
 * of it, is ever used here. See config/seo.php for how to obtain one.
 */
final class SearchConsoleClient
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const API_BASE = 'https://www.googleapis.com/webmasters/v1/sites/';

    public function __construct(
        private readonly Http $http,
        private readonly Config $config,
    ) {
    }

    public function enabled(): bool
    {
        return $this->config->get('seo.search_console.enabled', false) === true;
    }

    /**
     * Per-page, per-day performance for the date range given (inclusive).
     * Dates are 'Y-m-d' strings, matching what the API itself expects.
     *
     * @return list<array{page: string, date: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function fetch(string $startDate, string $endDate): array
    {
        $token = $this->accessToken();
        $siteUrl = $this->requireConfig('site_url');

        $response = $this->http
            ->withToken($token)
            ->timeout(30)
            ->post(self::API_BASE.rawurlencode($siteUrl).'/searchAnalytics/query', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                // 'page' and 'date' only — not 'query', which multiplies row
                // count by every distinct search term and belongs to keyword
                // tracking, a different (and, for real rank data, paid)
                // feature this package deliberately does not attempt.
                'dimensions' => ['page', 'date'],
                'rowLimit' => 25000,
            ]);

        if ($response->failed()) {
            throw SearchConsoleSyncFailed::http($response->status(), (string) $response->body());
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json('rows') ?? [];

        return array_map(static function (array $row): array {
            /** @var list<string> $keys */
            $keys = $row['keys'] ?? ['', ''];

            return [
                'page' => (string) ($keys[0] ?? ''),
                'date' => (string) ($keys[1] ?? ''),
                'clicks' => (int) ($row['clicks'] ?? 0),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0),
                'position' => (float) ($row['position'] ?? 0),
            ];
        }, $rows);
    }

    private function accessToken(): string
    {
        $response = $this->http->asForm()->timeout(15)->post(self::TOKEN_ENDPOINT, [
            'client_id' => $this->requireConfig('client_id'),
            'client_secret' => $this->requireConfig('client_secret'),
            'refresh_token' => $this->requireConfig('refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            throw SearchConsoleSyncFailed::tokenRefreshFailed($response->status(), (string) $response->body());
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw SearchConsoleSyncFailed::tokenRefreshFailed($response->status(), 'Response carried no access_token.');
        }

        return $token;
    }

    private function requireConfig(string $key): string
    {
        $value = $this->config->get("seo.search_console.{$key}");

        if (! is_string($value) || $value === '') {
            throw SearchConsoleSyncFailed::notConfigured("seo.search_console.{$key}");
        }

        return $value;
    }
}
