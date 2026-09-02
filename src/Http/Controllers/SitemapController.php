<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers;

use Duxbo\Seo\Sitemap\SitemapGenerator;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SitemapController
{
    public function __construct(
        private readonly SitemapGenerator $generator,
        private readonly Cache $cache,
        private readonly Config $config,
    ) {
    }

    public function index(): Response
    {
        return $this->xml('seo:sitemap:index', fn (): string => $this->generator->index());
    }

    public function source(string $name, ?int $part = null): Response
    {
        $source = $this->generator->source($name);

        if ($source === null) {
            // A 404 rather than an empty sitemap: an empty file tells a crawler
            // the section has no pages, which is a different and worse claim.
            throw new NotFoundHttpException("No sitemap source named [{$name}].");
        }

        $part = max(1, $part ?? 1);

        return $this->xml(
            "seo:sitemap:{$name}:{$part}",
            fn (): string => $this->generator->forSource($source, $part),
        );
    }

    /**
     * @param  callable(): string  $build
     */
    private function xml(string $key, callable $build): Response
    {
        $ttl = (int) $this->config->get('seo.sitemap.cache_ttl', 3600);

        $body = $ttl > 0
            ? $this->cache->remember($key, $ttl, $build)
            : $build();

        return new Response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
