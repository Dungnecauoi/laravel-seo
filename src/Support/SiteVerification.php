<?php

declare(strict_types=1);

namespace Duxbo\Seo\Support;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * The meta tags search consoles and social platforms ask for to confirm
 * domain ownership.
 *
 * Site-wide rather than per-record — unlike everything the resolution
 * pipeline produces, a verification code has no per-page variant, so this
 * sits outside that pipeline entirely and is read directly by the formatters
 * that render a full `<head>`.
 */
final class SiteVerification
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Meta tag name => content, only for the consoles actually configured.
     *
     * @return array<string, string>
     */
    public function metaTags(): array
    {
        /** @var array<string, mixed> $configured */
        $configured = $this->config->get('seo.verification', []);

        return array_filter([
            'google-site-verification' => $configured['google'] ?? null,
            'msvalidate.1' => $configured['bing'] ?? null,
            'yandex-verification' => $configured['yandex'] ?? null,
            'p:domain_verify' => $configured['pinterest'] ?? null,
            'facebook-domain-verification' => $configured['facebook'] ?? null,
        ], static fn (mixed $value): bool => is_string($value) && $value !== '');
    }
}
