<?php

declare(strict_types=1);

namespace Duxbo\Seo\Robots;

use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Support\SiteIndexability;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Builds robots.txt.
 *
 * Outside an indexable site the whole thing is disallowed — either because
 * `seo.enabled` is off (a demo domain shown to a client) or the current
 * environment is outside `seo.indexable_environments` (staging). Forgetting to
 * switch indexing on costs a day of traffic; forgetting to switch it off lets
 * a copy nobody meant to publish compete with production in the index for
 * months, and the second mistake is both more likely and far more expensive.
 */
final class RobotsTxt
{
    public function __construct(
        private readonly Config $config,
        private readonly SiteIndexability $indexability,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function render(): string
    {
        if (! $this->indexable()) {
            return implode("\n", [
                '# This environment is not indexable.',
                'User-agent: *',
                'Disallow: /',
                '',
            ]);
        }

        $lines = [];

        /** @var array<string, array<string, mixed>> $groups */
        $groups = $this->config->get('seo.robots.groups', []);

        if ($groups === []) {
            $groups = ['*' => ['disallow' => []]];
        }

        foreach ($groups as $agent => $rules) {
            $lines[] = 'User-agent: '.$agent;

            foreach ((array) ($rules['allow'] ?? []) as $path) {
                $lines[] = 'Allow: '.$path;
            }

            foreach ((array) ($rules['disallow'] ?? []) as $path) {
                $lines[] = 'Disallow: '.$path;
            }

            if (isset($rules['crawl_delay'])) {
                $lines[] = 'Crawl-delay: '.$rules['crawl_delay'];
            }

            $lines[] = '';
        }

        if ($this->config->get('seo.robots.block_ai_crawlers', false) === true) {
            /** @var list<string> $bots */
            $bots = $this->config->get('seo.robots.ai_crawlers', []);

            foreach ($bots as $bot) {
                $lines[] = 'User-agent: '.$bot;
                $lines[] = 'Disallow: /';
                $lines[] = '';
            }
        }

        foreach ($this->sitemapUrls() as $url) {
            $lines[] = 'Sitemap: '.$url;
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return list<string>
     */
    private function sitemapUrls(): array
    {
        if ($this->config->get('seo.sitemap.enabled', true) !== true) {
            return [];
        }

        /** @var list<string> $extra */
        $extra = $this->config->get('seo.robots.sitemaps', []);

        return [$this->urls->absolute('/sitemap.xml'), ...$extra];
    }

    private function indexable(): bool
    {
        return $this->indexability->ok();
    }
}
