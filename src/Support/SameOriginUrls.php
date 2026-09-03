<?php

declare(strict_types=1);

namespace Duxbo\Seo\Support;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Whether a URL stays on this site, or an explicitly allowed one.
 *
 * Shared by redirect targets and canonical URLs, because both are the same
 * trust boundary from the same threat: a low-privilege editor, or a
 * compromised one, pointing a field a search engine treats as authoritative
 * at a domain they do not control. A canonical set to an outside URL tells
 * Google "the real version of this page lives elsewhere" and can pull the
 * page out of the index entirely — a quieter, slower version of the redirect
 * guard's "turn a trusted URL into a phishing link", but the same class of
 * mistake, and worth the same unconditional check.
 */
final class SameOriginUrls
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * A relative path is always allowed. An absolute URL is allowed only when
     * its host is this site's own or explicitly configured. A protocol-
     * relative URL ("//evil.com") is never allowed — it looks like a path and
     * is not one.
     */
    public function isAllowed(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '//')) {
            return $url === '';
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        return in_array(strtolower($host), $this->allowedHosts(), true);
    }

    /**
     * @return list<string>
     */
    public function allowedHosts(): array
    {
        /** @var list<string> $configured */
        $configured = $this->config->get('seo.redirects.allowed_hosts', []);

        $appHost = parse_url((string) $this->config->get('app.url'), PHP_URL_HOST);

        $hosts = array_map('strtolower', $configured);

        if (is_string($appHost) && $appHost !== '') {
            $hosts[] = strtolower($appHost);
        }

        return array_values(array_unique($hosts));
    }
}
