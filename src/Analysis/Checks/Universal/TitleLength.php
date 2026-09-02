<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Support\PixelWidth;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

/**
 * Measured in pixels, because that is how Google truncates.
 *
 * A character count is wrong in both directions — "WWWWW" and "iiiii" are the
 * same length by strlen and three times apart on screen — and wrong by more in
 * Vietnamese, where diacritics add bytes without adding width.
 */
final class TitleLength extends Check
{
    public function __construct(private readonly int $maxPixels = 580)
    {
    }

    public function id(): string
    {
        return 'title-length';
    }

    public function weight(): int
    {
        return 2;
    }

    public function run(AnalysisContext $context): CheckResult
    {
        if ($context->title === null || $context->title === '') {
            return CheckResult::fail(
                $this->id(),
                'seo::analysis.title_length.missing',
                'seo::analysis.title_length.hint_missing',
            );
        }

        $width = PixelWidth::of($context->title);
        $data = ['pixels' => $width, 'max' => $this->maxPixels];

        if ($width > $this->maxPixels) {
            return CheckResult::warning(
                $this->id(),
                'seo::analysis.title_length.long',
                'seo::analysis.title_length.hint_long',
                $data,
            );
        }

        // Well under the limit is wasted space rather than an error.
        if ($width < $this->maxPixels * 0.4) {
            return CheckResult::warning(
                $this->id(),
                'seo::analysis.title_length.short',
                'seo::analysis.title_length.hint_short',
                $data,
            );
        }

        return CheckResult::pass($this->id(), 'seo::analysis.title_length.pass', $data);
    }
}
