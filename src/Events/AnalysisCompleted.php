<?php

declare(strict_types=1);

namespace Duxbo\Seo\Events;

use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\AnalysisReport;

/**
 * Fired after a page is scored — for storing the score, or blocking publication
 * below a threshold.
 */
final class AnalysisCompleted
{
    public function __construct(
        public readonly AnalysisContext $context,
        public readonly AnalysisReport $report,
    ) {
    }
}
