<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Universal;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Analysis\Tokenizer;
use Duxbo\Seo\Analysis\Vietnamese;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

/**
 * Density measured by the share of the text a keyword occupies, not by
 * occurrence count — counting occurrences would treat a three-syllable
 * phrase the same as a single word, and under-report every multi-word
 * keyword. {@see Tokenizer} supplies both the total and the keyword's own
 * length so the ratio stays meaningful for a script with no whitespace
 * between words, not only for Vietnamese and English.
 */
final class KeywordDensity extends Check
{
    public function __construct(
        private readonly float $min = 0.5,
        private readonly float $max = 2.5,
    ) {
    }

    public function id(): string
    {
        return 'keyword-density';
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
        $total = Tokenizer::count($text);

        // Too short for a density figure to mean anything.
        if ($total < 50) {
            return CheckResult::skipped($this->id());
        }

        $keyword = (string) $context->focusKeyword;
        $keywordLength = max(1, Tokenizer::count($keyword));
        $occurrences = Vietnamese::phraseCount($text, $keyword);

        $density = ($occurrences * $keywordLength) / $total * 100;
        $data = ['density' => round($density, 2), 'occurrences' => $occurrences];

        if ($density < $this->min) {
            return CheckResult::warning(
                $this->id(),
                'seo::analysis.keyword_density.low',
                'seo::analysis.keyword_density.hint_low',
                $data,
            );
        }

        if ($density > $this->max) {
            return CheckResult::fail(
                $this->id(),
                'seo::analysis.keyword_density.high',
                'seo::analysis.keyword_density.hint_high',
                $data,
            );
        }

        return CheckResult::pass($this->id(), 'seo::analysis.keyword_density.pass', $data);
    }
}
