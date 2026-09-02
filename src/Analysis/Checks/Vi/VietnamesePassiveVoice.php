<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks\Vi;

use Duxbo\Seo\Analysis\Checks\Check;
use Duxbo\Seo\Analysis\Vietnamese;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

/**
 * Spots the passive markers `được` and `bị`.
 *
 * Marker-spotting, not parsing: `được` also means "to receive" and appears in
 * plenty of active sentences, so this over-reports by design. It never fails a
 * page — at most it warns, and only when the proportion is high enough that the
 * false positives cannot explain it.
 */
final class VietnamesePassiveVoice extends Check
{
    public function __construct(private readonly float $threshold = 0.3)
    {
    }

    public function id(): string
    {
        return 'vi-passive-voice';
    }

    public function locales(): array
    {
        return ['vi'];
    }

    public function run(AnalysisContext $context): CheckResult
    {
        $sentences = Vietnamese::sentences($context->content->plainText);

        if (count($sentences) < 5) {
            return CheckResult::skipped($this->id());
        }

        $passive = Vietnamese::passiveSentences($context->content->plainText);
        $ratio = count($passive) / count($sentences);

        $data = [
            'passive' => count($passive),
            'total' => count($sentences),
            'ratio' => round($ratio * 100, 1),
        ];

        return $ratio > $this->threshold
            ? CheckResult::warning(
                $this->id(),
                'seo::analysis.vi_passive.high',
                'seo::analysis.vi_passive.hint',
                $data,
            )
            : CheckResult::pass($this->id(), 'seo::analysis.vi_passive.pass', $data);
    }
}
