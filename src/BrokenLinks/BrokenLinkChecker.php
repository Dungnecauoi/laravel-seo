<?php

declare(strict_types=1);

namespace Duxbo\Seo\BrokenLinks;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;

/**
 * Checks whether one external URL still resolves.
 *
 * HEAD first, since it is the whole point (no body to download for a link
 * this package only needs the status of) — but plenty of real servers
 * either don't implement HEAD or answer it differently from GET (405, 501,
 * or a misleading 200), so those specific statuses fall back to a real GET,
 * the same accommodation a browser makes.
 *
 * Redirects are followed by hand rather than left to the HTTP client's own
 * default — {@see PublicUrlGuard} has to approve every hop, not just the
 * URL a record's content actually spelled out, since a public URL can 302
 * to an internal one and the whole reason this class validates its input
 * is that the input is not trusted.
 */
final class BrokenLinkChecker
{
    private const RETRY_WITH_GET = [405, 501];

    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    private const MAX_REDIRECTS = 5;

    public function __construct(
        private readonly Http $http,
        private readonly Config $config,
        private readonly PublicUrlGuard $guard,
    ) {
    }

    /**
     * @return array{url: string, successful: bool, statusCode: int|null, error: string|null}
     */
    public function check(string $url): array
    {
        $timeout = (int) $this->config->get('seo.broken_links.timeout', 10);

        try {
            $response = $this->request($url, 'head', $timeout);

            if ($response === null) {
                return $this->blocked($url);
            }

            if (in_array($response->status(), self::RETRY_WITH_GET, true)) {
                $response = $this->request($url, 'get', $timeout);

                if ($response === null) {
                    return $this->blocked($url);
                }
            }

            return [
                'url' => $url,
                'successful' => $response->successful(),
                'statusCode' => $response->status(),
                'error' => $response->successful() ? null : "HTTP {$response->status()}",
            ];
        } catch (ConnectionException $e) {
            return ['url' => $url, 'successful' => false, 'statusCode' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{url: string, successful: bool, statusCode: int|null, error: string|null}
     */
    private function blocked(string $url): array
    {
        return [
            'url' => $url,
            'successful' => false,
            'statusCode' => null,
            'error' => 'Blocked: this URL (or a redirect it led to) resolves to a non-public address.',
        ];
    }

    /**
     * One logical check, following redirects itself — {@see
     * PublicUrlGuard::allows()} runs again before every hop. Returns null
     * when a hop is blocked or the redirect chain runs past the limit,
     * rather than the response that triggered it: nothing about a blocked
     * hop is safe to report back to a caller as if it were a real result.
     */
    private function request(string $url, string $method, int $timeout): ?Response
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! $this->guard->allows($url)) {
                return null;
            }

            $response = $this->http
                ->timeout($timeout)
                ->withOptions(['allow_redirects' => false])
                ->{$method}($url);

            if (! in_array($response->status(), self::REDIRECT_STATUSES, true)) {
                return $response;
            }

            $location = $response->header('Location');

            if ($location === '' || $location === null) {
                return $response;
            }

            $url = $this->resolveLocation($url, $location);
        }

        return null;
    }

    private function resolveLocation(string $base, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $baseParts = parse_url($base);
        $scheme = $baseParts['scheme'] ?? 'http';
        $host = $baseParts['host'] ?? '';
        $port = isset($baseParts['port']) ? ':'.$baseParts['port'] : '';
        $origin = "{$scheme}://{$host}{$port}";

        if (str_starts_with($location, '//')) {
            return "{$scheme}:{$location}";
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $basePath = $baseParts['path'] ?? '/';
        $dir = rtrim(dirname($basePath), '/');

        return "{$origin}{$dir}/{$location}";
    }
}
