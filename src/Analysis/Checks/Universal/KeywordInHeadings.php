<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

final class KeywordInHeadings extends Check
{
    public function id(): string
    {
        return 'keyword-in-headings';
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

        $subheadings = $context->content->headingsOfLevel(2, 3);

        if ($subheadings === []) {
            return CheckResult::warning(
                $this->id(),
                'seo::analysis.keyword_in_headings.none',
                'seo::analysis.keyword_in_headings.hint',
            );
        }

        foreach ($subheadings as $heading) {
            if ($this->containsKeyword($context, $heading->text)) {
                return CheckResult::pass($this->id(), 'seo::analysis.keyword_in_headings.pass');
            }
        }

        return CheckResult::warning(
            $this->id(),
            'seo::analysis.keyword_in_headings.fail',
            'seo::analysis.keyword_in_headings.hint',
            ['headings' => count($subheadings)],
        );
    }
}
