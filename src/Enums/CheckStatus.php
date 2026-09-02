<?php

declare(strict_types=1);

namespace Duxbo\Seo\Enums;

/**
 * Outcome of a single content analysis check.
 */
enum CheckStatus: string
{
    case Pass = 'pass';
    case Warning = 'warning';
    case Fail = 'fail';

    /** The check does not apply here — wrong locale, or nothing to measure. */
    case Skipped = 'skipped';

    /**
     * Whether the check contributes to the overall score.
     *
     * Skipped checks are excluded from both numerator and denominator, so a
     * page is never punished for a check that could not run.
     */
    public function counts(): bool
    {
        return $this !== self::Skipped;
    }

    /**
     * Fraction of the check's weight that is earned.
     */
    public function multiplier(): float
    {
        return match ($this) {
            self::Pass => 1.0,
            self::Warning => 0.5,
            self::Fail => 0.0,
            self::Skipped => 0.0,
        };
    }
}
