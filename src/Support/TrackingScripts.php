<?php

declare(strict_types=1);

namespace Duxbo\Seo\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\HtmlString;

/**
 * Raw marketing/analytics tags — GA4, Google Tag Manager, Meta Pixel,
 * TikTok Pixel and the like — echoed back exactly as configured.
 *
 * Site-wide rather than per-record, the same reasoning behind {@see
 * SiteVerification}: none of this has a per-page variant. This class does
 * not parse, validate, or understand any provider's snippet — it is a
 * trusted pass-through, the same trust level `schema.organization.*`
 * already has, never a place for anything sourced from an untrusted user.
 */
final class TrackingScripts
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Whatever is configured for `<head>` — most providers only need this.
     */
    public function head(): HtmlString
    {
        return new HtmlString($this->stringConfig('head'));
    }

    /**
     * Whatever is configured for right after `<body>` opens — Google Tag
     * Manager's own install instructions ask for a `<noscript><iframe>`
     * there, on top of the head snippet.
     */
    public function bodyOpen(): HtmlString
    {
        return new HtmlString($this->stringConfig('body_open'));
    }

    private function stringConfig(string $key): string
    {
        $value = $this->config->get("seo.tracking.{$key}");

        return is_string($value) ? $value : '';
    }
}
