<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

final class KeywordInImageAlt extends Check
{
    public function id(): string
    {
        return 'keyword-in-image-alt';
    }

    public function run(AnalysisContext $context): CheckResult
    {
        if ($skip = $this->skipWithoutKeyword($context)) {
            return $skip;
        }

        $images = $context->content->images;

        if ($images === []) {
            return CheckResult::skipped($this->id());
        }

        foreach ($images as $image) {
            if ($this->containsKeyword($context, $image->alt)) {
                return CheckResult::pass($this->id(), 'seo::analysis.keyword_in_image_alt.pass');
            }
        }

        return CheckResult::warning(
            $this->id(),
            'seo::analysis.keyword_in_image_alt.fail',
            'seo::analysis.keyword_in_image_alt.hint',
        );
    }
}
