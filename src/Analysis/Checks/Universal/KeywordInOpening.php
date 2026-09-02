<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

final class KeywordInOpening extends Check
{
    public function id(): string
    {
        return 'keyword-in-opening';
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

        $text = $context->content->plainText;

        if ($text === '') {
            return CheckResult::skipped($this->id());
        }

        // The first tenth of the article, roughly what a reader sees before
        // deciding whether to stay.
        $opening = mb_substr($text, 0, max(200, (int) (mb_strlen($text) * 0.1)));

        return $this->containsKeyword($context, $opening)
            ? CheckResult::pass($this->id(), 'seo::analysis.keyword_in_opening.pass')
            : CheckResult::warning(
                $this->id(),
                'seo::analysis.keyword_in_opening.fail',
                'seo::analysis.keyword_in_opening.hint',
            );
    }
}
