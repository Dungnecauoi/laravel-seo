<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

final class SingleH1 extends Check
{
    public function id(): string
    {
        return 'single-h1';
    }

    public function run(AnalysisContext $context): CheckResult
    {
        $count = count($context->content->headingsOfLevel(1));

        // Zero is as wrong as many: with no H1 the page has no stated subject.
        if ($count === 1) {
            return CheckResult::pass($this->id(), 'seo::analysis.single_h1.pass');
        }

        return CheckResult::warning(
            $this->id(),
            $count === 0 ? 'seo::analysis.single_h1.none' : 'seo::analysis.single_h1.many',
            'seo::analysis.single_h1.hint',
            ['count' => $count],
        );
    }
}
