<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;

/**
 * One scoring criterion.
 *
 * Checks are independent and declare which locales they understand, so
 * English-only measures (Flesch, passive voice) simply sit out on Vietnamese
 * content instead of producing nonsense.
 */
interface AnalysisCheck
{
    /**
     * Stable identifier, e.g. `keyword-in-title`. Used to remove or reweight
     * the check from config, so it must not change between releases.
     */
    public function id(): string;

    /**
     * Locales this check applies to. `['*']` means every locale.
     *
     * @return list<string>
     */
    public function locales(): array;

    /**
     * Relative weight in the final score.
     */
    public function weight(): int;

    public function run(AnalysisContext $context): CheckResult;
}
