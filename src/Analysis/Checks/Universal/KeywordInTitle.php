<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

/**
 * The single strongest on-page signal. A miss here is a failure, not a hint.
 */
final class KeywordInTitle extends Check
{
    public function id(): string
    {
        return 'keyword-in-title';
    }

    public function weight(): int
    {
        return 3;
    }

    public function run(AnalysisContext $context): CheckResult
    {
        if ($skip = $this->skipWithoutKeyword($context)) {
            return $skip;
        }

        return $this->containsKeyword($context, $context->title)
            ? CheckResult::pass($this->id(), 'seo::analysis.keyword_in_title.pass')
            : CheckResult::fail(
                $this->id(),
                'seo::analysis.keyword_in_title.fail',
                'seo::analysis.keyword_in_title.hint',
            );
    }
}
