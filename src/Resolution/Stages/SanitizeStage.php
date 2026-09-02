<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution\Stages;

use Closure;
use Duxbo\Seo\Contracts\ResolverStage;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Support\Text;

/**
 * Strips markup, collapses whitespace, normalises Unicode.
 *
 * Descriptions are routinely mapped straight from a rich-text body, so tags and
 * entities arrive constantly. Note that this is not the defence against
 * injection — the formatter escapes on output regardless of what reaches it.
 */
final class SanitizeStage implements ResolverStage
{
    public function handle(SeoContext $context, Closure $next): SeoContext
    {
        $data = $context->data;

        $clean = $data->with(
            title: self::clean($data->title),
            description: self::clean($data->description),
        );

        $og = $data->openGraph;

        if ($og !== null) {
            $clean = $clean->with(openGraph: $og->with(
                title: self::clean($og->title),
                description: self::clean($og->description),
                imageAlt: self::clean($og->imageAlt),
            ));
        }

        $twitter = $data->twitter;

        if ($twitter !== null) {
            $clean = $clean->with(twitter: $twitter->with(
                title: self::clean($twitter->title),
                description: self::clean($twitter->description),
                imageAlt: self::clean($twitter->imageAlt),
            ));
        }

        return $next($context->withData($clean));
    }

    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = Text::normalize(Text::plain($value));

        return $clean === '' ? null : $clean;
    }
}
