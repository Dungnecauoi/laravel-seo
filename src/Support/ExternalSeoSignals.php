<?php

declare(strict_types=1);

namespace Duxbo\Seo\Support;

use Duxbo\Seo\Contracts\Seoable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;

/**
 * The three "has anything already checked this page" signals {@see
 * \Duxbo\Seo\Console\AuditCommand} joins into a batch, available for one
 * specific record too — the per-record editor (Blade and
 * `useMetaStore`/`SeoPanel` alike) reads the same thing a batch audit does,
 * rather than duplicating the three queries a third time.
 *
 * Never triggers a live check itself, only reads what `seo:pagespeed` /
 * `seo:search-console:inspect` / `seo:broken-links` already stored — a
 * page load waiting on this must never also wait on a Google API call.
 * `null` on any field means "never checked", not "checked and fine".
 */
final class ExternalSeoSignals
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @return array{pagespeedScore: int|null, gscVerdict: string|null, brokenLinksCount: int|null}
     */
    public function for(string $url, Seoable $record): array
    {
        return [
            'pagespeedScore' => $this->latestPagespeedScore($url),
            'gscVerdict' => $this->latestGscVerdict($url),
            'brokenLinksCount' => $this->brokenLinksCount($record),
        ];
    }

    private function latestPagespeedScore(string $url): ?int
    {
        $table = (string) $this->config->get('seo.pagespeed.table', 'seo_pagespeed_stats');

        $score = DB::table($table)
            ->where('url_hash', md5($url))
            ->where('strategy', 'mobile')
            ->orderByDesc('date')
            ->value('performance_score');

        return $score !== null ? (int) $score : null;
    }

    private function latestGscVerdict(string $url): ?string
    {
        $table = (string) $this->config->get('seo.search_console.inspections_table', 'seo_url_inspections');

        $verdict = DB::table($table)
            ->where('url_hash', md5($url))
            ->orderByDesc('date')
            ->value('verdict');

        return is_string($verdict) && $verdict !== '' ? $verdict : null;
    }

    private function brokenLinksCount(Seoable $record): ?int
    {
        $externalTable = (string) $this->config->get('seo.broken_links.external_table', 'seo_external_links');
        $checksTable = (string) $this->config->get('seo.broken_links.checks_table', 'seo_link_checks');

        $sourceType = $record->seoType();
        $sourceId = (string) $record->seoKey();

        $crawled = DB::table($externalTable)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();

        if (! $crawled) {
            return null;
        }

        return DB::table($externalTable)
            ->join($checksTable, "{$externalTable}.target_hash", '=', "{$checksTable}.url_hash")
            ->where("{$externalTable}.source_type", $sourceType)
            ->where("{$externalTable}.source_id", $sourceId)
            ->where("{$checksTable}.successful", false)
            ->count();
    }
}
