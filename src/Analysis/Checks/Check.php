<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis\Checks;

use Duxbo\Seo\Analysis\Vietnamese;
use Duxbo\Seo\Contracts\AnalysisCheck;
use Duxbo\Seo\Data\AnalysisContext;
use Duxbo\Seo\Data\CheckResult;
use Duxbo\Seo\Support\Text;

/**
 * Shared plumbing for the built-in checks.
 *
 * Locale handling lives here so a check declares which languages it understands
 * and never has to remember to opt out of the others.
 */
abstract class Check implements AnalysisCheck
{
    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return ['*'];
    }

    public function weight(): int
    {
        return 1;
    }

    public function appliesTo(?string $locale): bool
    {
        $supported = $this->locales();

        if (in_array('*', $supported, true)) {
            return true;
        }

        if ($locale === null) {
            return false;
        }

        // 'vi' covers 'vi-VN'.
        $base = strtok($locale, '-') ?: $locale;

        return in_array($locale, $supported, true) || in_array($base, $supported, true);
    }

    /**
     * Count a keyword in a haystack, matching as a literal phrase.
     */
    protected function countKeyword(AnalysisContext $context, string $haystack): int
    {
        if (! $context->hasKeyword()) {
            return 0;
        }

        return Vietnamese::phraseCount($haystack, (string) $context->focusKeyword);
    }

    protected function containsKeyword(AnalysisContext $context, ?string $haystack): bool
    {
        if ($haystack === null || ! $context->hasKeyword()) {
            return false;
        }

        return $this->countKeyword($context, $haystack) > 0;
    }

    /**
     * A slug carries no diacritics, so the keyword must lose its own to match.
     */
    protected function containsKeywordInSlug(AnalysisContext $context, ?string $path): bool
    {
        if ($path === null || ! $context->hasKeyword()) {
            return false;
        }

        $slug = str_replace('-', ' ', Text::stripDiacritics($path));
        $keyword = Text::stripDiacritics((string) $context->focusKeyword);

        return str_contains($slug, $keyword);
    }

    protected function skipWithoutKeyword(AnalysisContext $context): ?CheckResult
    {
        return $context->hasKeyword() ? null : CheckResult::skipped($this->id());
    }
}
