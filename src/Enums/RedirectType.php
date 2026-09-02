<?php

declare(strict_types=1);

namespace Duxbo\Seo\Enums;

/**
 * HTTP status codes a redirect rule may respond with.
 */
enum RedirectType: int
{
    case MovedPermanently = 301;
    case Found = 302;
    case Temporary = 307;
    case Permanent = 308;
    case Gone = 410;
    case UnavailableForLegalReasons = 451;

    /**
     * Whether the status sends the visitor to a target URL.
     *
     * 410 and 451 terminate the request instead, so they carry no target.
     */
    public function redirects(): bool
    {
        return match ($this) {
            self::Gone, self::UnavailableForLegalReasons => false,
            default => true,
        };
    }

    /**
     * Whether search engines should transfer ranking signals to the target.
     */
    public function isPermanent(): bool
    {
        return $this === self::MovedPermanently || $this === self::Permanent;
    }

    public function label(): string
    {
        return match ($this) {
            self::MovedPermanently => '301 Moved Permanently',
            self::Found => '302 Found',
            self::Temporary => '307 Temporary Redirect',
            self::Permanent => '308 Permanent Redirect',
            self::Gone => '410 Gone',
            self::UnavailableForLegalReasons => '451 Unavailable For Legal Reasons',
        };
    }
}
