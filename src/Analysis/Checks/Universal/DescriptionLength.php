<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

/**
 * Characters, not pixels: the description limit is a published guideline
 * rather than a rendering constraint, and every recommendation states it in
 * characters.
 */
final class DescriptionLength extends Check
{
    public function __construct(
        private readonly int $min = 120,
        private readonly int $max = 158,
    ) {
    }

    public function id(): string
    {
        return 'description-length';
    }

    public function weight(): int
    {
        return 2;
    }

    public function run(AnalysisContext $context): CheckResult
    {
        if ($context->description === null || $context->description === '') {
            return CheckResult::fail(
                $this->id(),
                'seo::analysis.description_length.missing',
                'seo::analysis.description_length.hint_missing',
            );
        }

        $length = mb_strlen($context->description);
        $data = ['length' => $length, 'min' => $this->min, 'max' => $this->max];

        if ($length < $this->min) {
            return CheckResult::warning(
                $this->id(),
                'seo::analysis.description_length.short',
                'seo::analysis.description_length.hint_short',
                $data,
            );
        }

        if ($length > $this->max) {
            return CheckResult::warning(
                $this->id(),
                'seo::analysis.description_length.long',
                'seo::analysis.description_length.hint_long',
                $data,
            );
        }

        return CheckResult::pass($this->id(), 'seo::analysis.description_length.pass', $data);
    }
}
