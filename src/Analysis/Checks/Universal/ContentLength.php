<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Analysis\Vietnamese;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

final class ContentLength extends Check
{
    public function __construct(private readonly int $minimum = 600)
    {
    }

    public function id(): string
    {
        return 'content-length';
    }

    public function weight(): int
    {
        return 2;
    }

    public function run(AnalysisContext $context): CheckResult
    {
        // Syllables rather than words: in Vietnamese those differ by roughly a
        // third, so a word count borrowed from English guidance misleads.
        $count = Vietnamese::syllableCount($context->content->plainText);
        $data = ['count' => $count, 'minimum' => $this->minimum];

        if ($count === 0) {
            return CheckResult::skipped($this->id());
        }

        if ($count < $this->minimum) {
            return CheckResult::warning(
                $this->id(),
                'seo::analysis.content_length.short',
                'seo::analysis.content_length.hint',
                $data,
            );
        }

        return CheckResult::pass($this->id(), 'seo::analysis.content_length.pass', $data);
    }
}
