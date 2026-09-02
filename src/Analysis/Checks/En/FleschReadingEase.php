<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\En;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

/**
 * The classic English readability score.
 *
 * Declared for English only. Running it on Vietnamese produces a confident
 * number that means nothing, which is worse than no number at all.
 */
final class FleschReadingEase extends Check
{
    public function __construct(private readonly float $minimum = 60.0)
    {
    }

    public function id(): string
    {
        return 'en-flesch';
    }

    public function locales(): array
    {
        return ['en'];
    }

    public function weight(): int
    {
        return 2;
    }

    public function run(AnalysisContext $context): CheckResult
    {
        $text = $context->content->plainText;

        $sentences = max(1, preg_match_all('/[.!?]+/u', $text));
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);

        if ($wordCount < 100) {
            return CheckResult::skipped($this->id());
        }

        $syllables = 0;

        foreach ($words as $word) {
            $syllables += self::syllablesIn($word);
        }

        $score = 206.835
            - 1.015 * ($wordCount / $sentences)
            - 84.6 * ($syllables / $wordCount);

        $data = ['score' => round($score, 1)];

        return $score >= $this->minimum
            ? CheckResult::pass($this->id(), 'seo::analysis.en_flesch.pass', $data)
            : CheckResult::warning(
                $this->id(),
                'seo::analysis.en_flesch.hard',
                'seo::analysis.en_flesch.hint',
                $data,
            );
    }

    private static function syllablesIn(string $word): int
    {
        $word = strtolower(preg_replace('/[^a-z]/i', '', $word) ?? '');

        if ($word === '') {
            return 0;
        }

        $word = (string) preg_replace('/(?:e)$/', '', $word);
        $groups = preg_match_all('/[aeiouy]+/', $word);

        return max(1, (int) $groups);
    }
}
