<?php

declare(strict_types=1);

namespace Duxbo\Seo\Enums;

/**
 * Directives valid in a `robots` meta tag or `X-Robots-Tag` header.
 *
 * Directives that carry a value (max-snippet, max-image-preview,
 * max-video-preview) are represented here as the bare keyword; the value is
 * attached by wrapping the case in a {@see \Duxbo\Seo\Data\RobotsRule}.
 */
enum RobotsDirective: string
{
    case Index = 'index';
    case NoIndex = 'noindex';
    case Follow = 'follow';
    case NoFollow = 'nofollow';
    case NoArchive = 'noarchive';
    case NoSnippet = 'nosnippet';
    case NoImageIndex = 'noimageindex';
    case NoTranslate = 'notranslate';
    case MaxSnippet = 'max-snippet';
    case MaxImagePreview = 'max-image-preview';
    case MaxVideoPreview = 'max-video-preview';

    /**
     * Whether the directive is meaningless without an accompanying value.
     */
    public function requiresValue(): bool
    {
        return match ($this) {
            self::MaxSnippet, self::MaxImagePreview, self::MaxVideoPreview => true,
            default => false,
        };
    }

    /**
     * The directive this one cancels out, if any.
     *
     * Used to drop contradictory pairs such as `index, noindex` before render.
     */
    public function opposite(): ?self
    {
        return match ($this) {
            self::Index => self::NoIndex,
            self::NoIndex => self::Index,
            self::Follow => self::NoFollow,
            self::NoFollow => self::Follow,
            default => null,
        };
    }
}
