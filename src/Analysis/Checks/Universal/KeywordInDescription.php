<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

final class KeywordInDescription extends Check
{
    public function id(): string
    {
        return 'keyword-in-description';
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

        return $this->containsKeyword($context, $context->description)
            ? CheckResult::pass($this->id(), 'seo::analysis.keyword_in_description.pass')
            : CheckResult::warning(
                $this->id(),
                'seo::analysis.keyword_in_description.fail',
                'seo::analysis.keyword_in_description.hint',
            );
    }
}
