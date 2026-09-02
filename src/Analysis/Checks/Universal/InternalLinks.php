<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

final class InternalLinks extends Check
{
    public function id(): string
    {
        return 'internal-links';
    }

    public function run(AnalysisContext $context): CheckResult
    {
        $count = count($context->content->internalLinks());

        return $count > 0
            ? CheckResult::pass($this->id(), 'seo::analysis.internal_links.pass', ['count' => $count])
            : CheckResult::warning(
                $this->id(),
                'seo::analysis.internal_links.none',
                'seo::analysis.internal_links.hint',
            );
    }
}
