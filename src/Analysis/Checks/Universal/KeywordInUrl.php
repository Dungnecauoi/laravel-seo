<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

/**
 * Slugs carry no diacritics, so the comparison strips them from both sides.
 */
final class KeywordInUrl extends Check
{
    public function id(): string
    {
        return 'keyword-in-url';
    }

    public function weight(): int
    {
        return 2;
    }

    public function run(AnalysisContext $context): CheckResult
    {
        if ($skip = $this->skipWithoutKeyword($context)) {
            return $skip;
        }

        return $this->containsKeywordInSlug($context, $context->path())
            ? CheckResult::pass($this->id(), 'seo::analysis.keyword_in_url.pass')
            : CheckResult::warning(
                $this->id(),
                'seo::analysis.keyword_in_url.fail',
                'seo::analysis.keyword_in_url.hint',
            );
    }
}
