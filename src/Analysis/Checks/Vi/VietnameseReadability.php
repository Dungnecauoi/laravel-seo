<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Vi;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Analysis\Vietnamese;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

/**
 * Sentence length in syllables, as a readability proxy for Vietnamese.
 *
 * Flesch Reading Ease and its descendants count syllables the way English
 * spells them and produce nonsense here, so this check replaces them rather
 * than adjusting them. The thresholds are a working heuristic, not a validated
 * instrument, and the message says so.
 */
final class VietnameseReadability extends Check
{
    public function __construct(
        private readonly int $comfortable = 20,
        private readonly int $hard = 25,
    ) {
    }

    public function id(): string
    {
        return 'vi-readability';
    }

    public function locales(): array
    {
        return ['vi'];
    }

    public function weight(): int
    {
        return 2;
    }

    public function run(AnalysisContext $context): CheckResult
    {
        $text = $context->content->plainText;

        if (Vietnamese::syllableCount($text) < 50) {
            return CheckResult::skipped($this->id());
        }

        $average = Vietnamese::averageSentenceLength($text);
        $long = Vietnamese::longSentences($text, $this->hard);

        $data = [
            'average' => round($average, 1),
            'long_sentences' => count($long),
        ];

        if ($average > $this->hard) {
            return CheckResult::fail(
                $this->id(),
                'seo::analysis.vi_readability.hard',
                'seo::analysis.vi_readability.hint',
                $data,
            );
        }

        if ($average > $this->comfortable) {
            return CheckResult::warning(
                $this->id(),
                'seo::analysis.vi_readability.medium',
                'seo::analysis.vi_readability.hint',
                $data,
            );
        }

        return CheckResult::pass($this->id(), 'seo::analysis.vi_readability.easy', $data);
    }
}
