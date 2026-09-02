<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution\Stages;

use Closure;
use Duxbo\Seo\Contracts\ResolverStage;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Support\PixelWidth;
use Duxbo\Seo\Support\Text;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Cuts titles and descriptions to what a search result actually shows.
 *
 * Titles are measured in pixels because that is how Google truncates them.
 * Descriptions are measured in characters because their limit is a soft
 * guideline rather than a rendering constraint, and character counts are what
 * every published recommendation is stated in.
 *
 * Remove this stage from the pipeline to keep full-length values.
 */
final class TruncateStage implements ResolverStage
{
    public function __construct(private readonly Config $config)
    {
    }

    public function handle(SeoContext $context, Closure $next): SeoContext
    {
        $data = $context->data;

        $maxPixels = (int) $this->config->get('seo.limits.title_pixels', 580);
        $maxChars = (int) $this->config->get('seo.limits.description_max', 158);

        $title = $data->title;
        $description = $data->description;

        return $next($context->withData($data->with(
            title: $title !== null ? PixelWidth::truncate($title, $maxPixels) : null,
            description: $description !== null ? self::limitCharacters($description, $maxChars) : null,
        )));
    }

    private static function limitCharacters(string $value, int $max): string
    {
        if (mb_strlen($value, 'UTF-8') <= $max) {
            return $value;
        }

        // Reserve one character for the ellipsis, then step back to a word
        // boundary so the description does not end mid-syllable.
        $cut = mb_substr($value, 0, $max - 1, 'UTF-8');
        $lastSpace = mb_strrpos($cut, ' ', 0, 'UTF-8');

        if ($lastSpace !== false && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace, 'UTF-8');
        }

        return Text::collapse($cut).'…';
    }
}
