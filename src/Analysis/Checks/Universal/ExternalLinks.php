<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

final class ExternalLinks extends Check
{
    public function id(): string
    {
        return 'external-links';
    }

    public function run(AnalysisContext $context): CheckResult
    {
        $count = count($context->content->externalLinks());

        return $count > 0
            ? CheckResult::pass($this->id(), 'seo::analysis.external_links.pass', ['count' => $count])
            : CheckResult::warning(
                $this->id(),
                'seo::analysis.external_links.none',
                'seo::analysis.external_links.hint',
            );
    }
}
