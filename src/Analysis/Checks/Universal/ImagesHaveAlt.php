<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

/**
 * Accessibility first, image search second — but the same fix serves both.
 */
final class ImagesHaveAlt extends Check
{
    public function id(): string
    {
        return 'images-have-alt';
    }

    public function weight(): int
    {
        return 2;
    }

    public function run(AnalysisContext $context): CheckResult
    {
        $images = $context->content->images;

        if ($images === []) {
            return CheckResult::skipped($this->id());
        }

        $missing = $context->content->imagesMissingAlt();

        if ($missing === []) {
            return CheckResult::pass($this->id(), 'seo::analysis.images_have_alt.pass', [
                'total' => count($images),
            ]);
        }

        return CheckResult::fail(
            $this->id(),
            'seo::analysis.images_have_alt.fail',
            'seo::analysis.images_have_alt.hint',
            ['missing' => count($missing), 'total' => count($images)],
        );
    }
}
