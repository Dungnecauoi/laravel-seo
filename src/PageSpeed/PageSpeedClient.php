<?php

declare(strict_types=1);

namespace Duxbo\Seo\PageSpeed;

use Duxbo\Seo\Exceptions\PageSpeedFetchFailed;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reads Lighthouse performance and Core Web Vitals from the PageSpeed
 * Insights API.
 *
 * Auth here is a plain API key, not the OAuth dance {@see
 * \Duxbo\Seo\SearchConsole\SearchConsoleClient} and {@see
 * \Duxbo\Seo\GoogleIndexing\GoogleIndexingClient} both need — PSI is a
 * public read-only API, so "enable PageSpeed Insights API" plus an API key
 * from Google Cloud Console is the whole setup.
 *
 * Two distinct kinds of number come back, and this class keeps them
 * distinct rather than flattening them into one score:
 *
 * - Lab data (`lighthouseResult`) — a synthetic run against this URL right
 *   now, always present, and what `performanceScore` and the millisecond
 *   metrics below come from.
 * - Field data (`loadingExperience`) — real Chrome UX Report data from
 *   actual visitors over the last 28 days, only present once a URL has
 *   enough traffic for Google to have collected it. `fieldDataAvailable`
 *   tells a caller which case it got; a low-traffic page legitimately has
 *   none, and that is not an error.
 */
final class PageSpeedClient
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public function __construct(
        private readonly Http $http,
        private readonly Config $config,
    ) {
    }

    public function enabled(): bool
    {
        return $this->config->get('seo.pagespeed.enabled', false) === true;
    }

    /**
     * Fetches a fresh score and stores it as that day's row for this
     * URL+strategy — the read path every command and AI tool that runs a
     * real check should go through, so storing a result is never a second
     * step a caller can forget.
     *
     * @return array{
     *     url: string,
     *     strategy: string,
     *     performanceScore: int|null,
     *     lcpMs: float|null,
     *     clsScore: float|null,
     *     tbtMs: float|null,
     *     fcpMs: float|null,
     *     speedIndexMs: float|null,
     *     fieldDataAvailable: bool,
     *     cwvCategory: string|null,
     * }
     */
    public function check(string $url, string $strategy = 'mobile'): array
    {
        $result = $this->fetch($url, $strategy);

        $this->store($result);

        return $result;
    }

    /**
     * The bare API call and parse, with no storage side effect — what
     * {@see check()} is built on, and useful on its own for a caller that
     * only wants a live number (a panel "test this page now" button)
     * without adding a row to the trend line.
     *
     * @return array{
     *     url: string,
     *     strategy: string,
     *     performanceScore: int|null,
     *     lcpMs: float|null,
     *     clsScore: float|null,
     *     tbtMs: float|null,
     *     fcpMs: float|null,
     *     speedIndexMs: float|null,
     *     fieldDataAvailable: bool,
     *     cwvCategory: string|null,
     * }
     */
    public function fetch(string $url, string $strategy = 'mobile'): array
    {
        $apiKey = $this->requireConfig('api_key');

        $response = $this->http->timeout(60)->get(self::ENDPOINT, [
            'url' => $url,
            'strategy' => $strategy,
            'category' => 'performance',
            'key' => $apiKey,
        ]);

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        // PSI sometimes answers 200 with an `error` object instead of a
        // non-2xx status — e.g. the target URL timed out or returned its own
        // error page to Lighthouse's crawler.
        if ($response->failed() || isset($json['error'])) {
            $message = is_array($json['error'] ?? null) && is_string($json['error']['message'] ?? null)
                ? $json['error']['message']
                : (string) $response->body();

            throw PageSpeedFetchFailed::http($response->status(), $message);
        }

        return $this->parse($url, $strategy, $json);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{
     *     url: string,
     *     strategy: string,
     *     performanceScore: int|null,
     *     lcpMs: float|null,
     *     clsScore: float|null,
     *     tbtMs: float|null,
     *     fcpMs: float|null,
     *     speedIndexMs: float|null,
     *     fieldDataAvailable: bool,
     *     cwvCategory: string|null,
     * }
     */
    private function parse(string $url, string $strategy, array $json): array
    {
        /** @var array<string, mixed> $lighthouse */
        $lighthouse = $json['lighthouseResult'] ?? [];
        /** @var array<string, mixed> $audits */
        $audits = $lighthouse['audits'] ?? [];
        $score = $lighthouse['categories']['performance']['score'] ?? null;

        /** @var array<string, mixed>|null $loadingExperience */
        $loadingExperience = $json['loadingExperience'] ?? null;

        return [
            'url' => $url,
            'strategy' => $strategy,
            'performanceScore' => is_numeric($score) ? (int) round(((float) $score) * 100) : null,
            'lcpMs' => $this->numericValue($audits, 'largest-contentful-paint'),
            'clsScore' => $this->numericValue($audits, 'cumulative-layout-shift'),
            'tbtMs' => $this->numericValue($audits, 'total-blocking-time'),
            'fcpMs' => $this->numericValue($audits, 'first-contentful-paint'),
            'speedIndexMs' => $this->numericValue($audits, 'speed-index'),
            'fieldDataAvailable' => $loadingExperience !== null,
            'cwvCategory' => is_string($loadingExperience['overall_category'] ?? null)
                ? $loadingExperience['overall_category']
                : null,
        ];
    }

    /**
     * @param  array{
     *     url: string,
     *     strategy: string,
     *     performanceScore: int|null,
     *     lcpMs: float|null,
     *     clsScore: float|null,
     *     tbtMs: float|null,
     *     fcpMs: float|null,
     *     speedIndexMs: float|null,
     *     fieldDataAvailable: bool,
     *     cwvCategory: string|null,
     * }  $result
     */
    private function store(array $result): void
    {
        $table = (string) $this->config->get('seo.pagespeed.table', 'seo_pagespeed_stats');
        $now = Carbon::now();

        // A day's run replaces that day's row when re-run, same as Search
        // Console's own sync — not accumulated into a second row.
        $key = ['url_hash' => md5($result['url']), 'strategy' => $result['strategy'], 'date' => $now->toDateString()];
        $exists = DB::table($table)->where($key)->exists();

        DB::table($table)->updateOrInsert($key, [
            'url' => $result['url'],
            'performance_score' => $result['performanceScore'],
            'lcp_ms' => $result['lcpMs'] !== null ? (int) round($result['lcpMs']) : null,
            'cls_score' => $result['clsScore'],
            'tbt_ms' => $result['tbtMs'] !== null ? (int) round($result['tbtMs']) : null,
            'fcp_ms' => $result['fcpMs'] !== null ? (int) round($result['fcpMs']) : null,
            'speed_index_ms' => $result['speedIndexMs'] !== null ? (int) round($result['speedIndexMs']) : null,
            'field_data_available' => $result['fieldDataAvailable'],
            'cwv_category' => $result['cwvCategory'],
            'updated_at' => $now,
            ...($exists ? [] : ['created_at' => $now]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $audits
     */
    private function numericValue(array $audits, string $auditId): ?float
    {
        $value = $audits[$auditId]['numericValue'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    private function requireConfig(string $key): string
    {
        $value = $this->config->get("seo.pagespeed.{$key}");

        if (! is_string($value) || $value === '') {
            throw PageSpeedFetchFailed::notConfigured("seo.pagespeed.{$key}");
        }

        return $value;
    }
}
