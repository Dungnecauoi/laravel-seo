<?php

declare(strict_types=1);

namespace Duxbo\Seo\IndexNow;

use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Exceptions\IndexNowSubmissionFailed;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;

/**
 * Pushes changed URLs to every IndexNow-participating engine — Bing, Yandex,
 * Seznam — with one shared API call rather than one per engine.
 *
 * Mirrors {@see \Duxbo\Seo\NotFound\NotFoundLogger}'s shape: a single
 * concrete class that checks its own `enabled` flag rather than a swappable
 * driver, because there is exactly one real behaviour here (call the one
 * documented endpoint), not a family of interchangeable ones the way the AI
 * drivers are.
 *
 * `enabled = false` (the default) makes `submit()` a silent no-op, the same
 * as calling the AI manager with no driver configured — installing this
 * package must never start an outbound request on its own. `enabled = true`
 * with no key is instead treated as a mistake worth failing loudly for,
 * since a developer who explicitly turned this on almost certainly meant to
 * set a key too.
 */
final class IndexNowSubmitter
{
    public function __construct(
        private readonly Http $http,
        private readonly Config $config,
        private readonly UrlGenerator $urls,
    ) {
    }

    /**
     * @param  string|list<string>  $urls  Absolute or site-relative.
     */
    public function submit(string|array $urls): bool
    {
        if ($this->config->get('seo.indexnow.enabled', false) !== true) {
            return false;
        }

        $key = $this->key();

        if ($key === null) {
            throw IndexNowSubmissionFailed::noKey();
        }

        $urlList = array_values(array_unique(array_map(
            fn (string $url): string => $this->urls->absolute($url),
            is_string($urls) ? [$urls] : $urls,
        )));

        if ($urlList === []) {
            return false;
        }

        $host = (string) parse_url($this->urls->home(), PHP_URL_HOST);

        $response = $this->http->timeout(10)->post($this->endpoint(), [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $this->urls->absolute("/{$key}.txt"),
            'urlList' => $urlList,
        ]);

        if ($response->failed()) {
            throw IndexNowSubmissionFailed::http($response->status(), (string) $response->body());
        }

        return true;
    }

    public function key(): ?string
    {
        $key = $this->config->get('seo.indexnow.key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function endpoint(): string
    {
        return (string) $this->config->get('seo.indexnow.endpoint', 'https://api.indexnow.org/indexnow');
    }
}
