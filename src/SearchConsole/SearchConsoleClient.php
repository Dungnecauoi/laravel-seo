<?php

declare(strict_types=1);

namespace Duxbo\Seo\SearchConsole;

use Duxbo\Seo\Exceptions\SearchConsoleSyncFailed;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    private const INSPECT_ENDPOINT = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

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

    /**
     * Whether Google has this URL indexed right now, and why not if it
     * doesn't — the thing `fetch()`'s performance numbers cannot tell a
     * caller, since a page with zero clicks might be ranking nowhere or
     * might simply not be indexed at all. Google rate-limits this endpoint
     * far tighter than search analytics (roughly 2,000 requests/day per
     * site) — built for spot-checking specific URLs, not crawling a sitemap
     * through it, the same reasoning behind {@see
     * \Duxbo\Seo\PageSpeed\PageSpeedClient} taking explicit URLs rather than
     * pulling a list from Google itself.
     *
     * @return array{
     *     verdict: string|null,
     *     coverageState: string|null,
     *     robotsTxtState: string|null,
     *     indexingState: string|null,
     *     pageFetchState: string|null,
     *     lastCrawlTime: string|null,
     *     googleCanonical: string|null,
     *     userCanonical: string|null,
     *     sitemaps: list<string>,
     *     referringUrls: list<string>,
     *     mobileUsabilityVerdict: string|null,
     *     mobileUsabilityIssues: list<string>,
     *     richResultsVerdict: string|null,
     *     inspectionResultLink: string|null,
     * }
     */
    public function inspectUrl(string $url): array
    {
        $token = $this->accessToken();
        $siteUrl = $this->requireConfig('site_url');

        $response = $this->http
            ->withToken($token)
            ->timeout(30)
            ->post(self::INSPECT_ENDPOINT, [
                'inspectionUrl' => $url,
                'siteUrl' => $siteUrl,
            ]);

        if ($response->failed()) {
            throw SearchConsoleSyncFailed::http($response->status(), (string) $response->body());
        }

        /** @var array<string, mixed> $result */
        $result = $response->json('inspectionResult') ?? [];

        return $this->parseInspection($result);
    }

    /**
     * {@see inspectUrl()} plus storing the result as that day's row for this
     * URL — the path every command and AI tool that runs a real inspection
     * should go through, mirroring {@see \Duxbo\Seo\PageSpeed\PageSpeedClient::check()}
     * so storing a result is never a second step a caller can forget.
     *
     * @return array{
     *     verdict: string|null,
     *     coverageState: string|null,
     *     robotsTxtState: string|null,
     *     indexingState: string|null,
     *     pageFetchState: string|null,
     *     lastCrawlTime: string|null,
     *     googleCanonical: string|null,
     *     userCanonical: string|null,
     *     sitemaps: list<string>,
     *     referringUrls: list<string>,
     *     mobileUsabilityVerdict: string|null,
     *     mobileUsabilityIssues: list<string>,
     *     richResultsVerdict: string|null,
     *     inspectionResultLink: string|null,
     * }
     */
    public function inspect(string $url): array
    {
        $result = $this->inspectUrl($url);

        $this->storeInspection($url, $result);

        return $result;
    }

    /**
     * @param  array{
     *     verdict: string|null,
     *     coverageState: string|null,
     *     robotsTxtState: string|null,
     *     indexingState: string|null,
     *     pageFetchState: string|null,
     *     lastCrawlTime: string|null,
     *     googleCanonical: string|null,
     *     userCanonical: string|null,
     *     sitemaps: list<string>,
     *     referringUrls: list<string>,
     *     mobileUsabilityVerdict: string|null,
     *     mobileUsabilityIssues: list<string>,
     *     richResultsVerdict: string|null,
     *     inspectionResultLink: string|null,
     * }  $result
     */
    private function storeInspection(string $url, array $result): void
    {
        $table = (string) $this->config->get('seo.search_console.inspections_table', 'seo_url_inspections');
        $now = Carbon::now();

        // A day's inspection replaces that day's row when re-run, same as
        // the performance sync — not accumulated into a second row.
        $key = ['url_hash' => md5($url), 'date' => $now->toDateString()];
        $exists = DB::table($table)->where($key)->exists();

        DB::table($table)->updateOrInsert($key, [
            'url' => $url,
            'verdict' => $result['verdict'],
            'coverage_state' => $result['coverageState'],
            'robots_txt_state' => $result['robotsTxtState'],
            'indexing_state' => $result['indexingState'],
            'page_fetch_state' => $result['pageFetchState'],
            'google_canonical' => $result['googleCanonical'],
            'user_canonical' => $result['userCanonical'],
            'mobile_usability_verdict' => $result['mobileUsabilityVerdict'],
            'mobile_usability_issues' => json_encode($result['mobileUsabilityIssues'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'rich_results_verdict' => $result['richResultsVerdict'],
            'updated_at' => $now,
            ...($exists ? [] : ['created_at' => $now]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{
     *     verdict: string|null,
     *     coverageState: string|null,
     *     robotsTxtState: string|null,
     *     indexingState: string|null,
     *     pageFetchState: string|null,
     *     lastCrawlTime: string|null,
     *     googleCanonical: string|null,
     *     userCanonical: string|null,
     *     sitemaps: list<string>,
     *     referringUrls: list<string>,
     *     mobileUsabilityVerdict: string|null,
     *     mobileUsabilityIssues: list<string>,
     *     richResultsVerdict: string|null,
     *     inspectionResultLink: string|null,
     * }
     */
    private function parseInspection(array $result): array
    {
        /** @var array<string, mixed> $indexStatus */
        $indexStatus = $result['indexStatusResult'] ?? [];
        /** @var array<string, mixed> $mobileUsability */
        $mobileUsability = $result['mobileUsabilityResult'] ?? [];
        /** @var array<string, mixed> $richResults */
        $richResults = $result['richResultsResult'] ?? [];
        /** @var list<array<string, mixed>> $mobileIssues */
        $mobileIssues = $mobileUsability['issues'] ?? [];

        return [
            'verdict' => $this->nullableString($indexStatus['verdict'] ?? null),
            'coverageState' => $this->nullableString($indexStatus['coverageState'] ?? null),
            'robotsTxtState' => $this->nullableString($indexStatus['robotsTxtState'] ?? null),
            'indexingState' => $this->nullableString($indexStatus['indexingState'] ?? null),
            'pageFetchState' => $this->nullableString($indexStatus['pageFetchState'] ?? null),
            'lastCrawlTime' => $this->nullableString($indexStatus['lastCrawlTime'] ?? null),
            'googleCanonical' => $this->nullableString($indexStatus['googleCanonical'] ?? null),
            'userCanonical' => $this->nullableString($indexStatus['userCanonical'] ?? null),
            'sitemaps' => array_values(array_map('strval', $indexStatus['sitemap'] ?? [])),
            'referringUrls' => array_values(array_map('strval', $indexStatus['referringUrls'] ?? [])),
            'mobileUsabilityVerdict' => $this->nullableString($mobileUsability['verdict'] ?? null),
            'mobileUsabilityIssues' => array_values(array_map(
                static fn (array $issue): string => (string) ($issue['issueType'] ?? 'UNKNOWN'),
                $mobileIssues,
            )),
            'richResultsVerdict' => $this->nullableString($richResults['verdict'] ?? null),
            'inspectionResultLink' => $this->nullableString($result['inspectionResultLink'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
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
