<?php

declare(strict_types=1);

namespace Duxbo\Seo\Support;

/**
 * Detects a PCRE pattern that can hang (or burn excessive CPU) on crafted
 * input — shared by {@see \Duxbo\Seo\Redirects\RedirectGuard} (a stored
 * redirect rule) and {@see \Duxbo\Seo\Settings\Validators\RegexListSettingValidator}
 * (`seo.not_found.exclude`) — the same risk applies to both: a pattern an
 * admin can save that then runs against every request afterward.
 *
 * Catastrophic backtracking comes in more shapes than any one static check
 * on the pattern's own text can enumerate — nested quantifiers
 * (`(a+)+`), ambiguous alternation (`(a|aa)+$`), and others besides. Rather
 * than recognising one specific dangerous construct, this actually *runs*
 * the pattern against a handful of short, deliberately non-matching probe
 * strings and checks whether PCRE's own backtrack-limit safety net had to
 * step in — the same signal that protects a live request, just triggered
 * deliberately, once, at the moment a pattern is saved rather than the
 * first time real traffic hits it. A pattern that exhausts the backtrack
 * limit on a 40-character probe is not safe to run against
 * attacker-controlled input (a 404 path, a redirect source) regardless of
 * which construct caused it.
 */
final class CatastrophicPattern
{
    private const PROBE_LENGTH = 40;

    public static function detected(string $pattern): bool
    {
        foreach (self::probes() as $probe) {
            @preg_match($pattern, $probe);

            if (preg_last_error() === PREG_BACKTRACK_LIMIT_ERROR) {
                return true;
            }
        }

        return false;
    }

    /**
     * A few different repeating shapes, since a pattern vulnerable to one
     * (a run of a single character) isn't necessarily vulnerable to
     * another (an alternating pair) — each ends in a character the probe
     * itself never contains, so the match genuinely fails rather than
     * succeeding early and skipping the backtracking a real non-match
     * would trigger.
     *
     * Each shape is probed both bare and with a leading "/" — every
     * pattern this class actually guards matches against a URL path
     * (`RedirectGuard`'s stored redirect source, `not_found.exclude`),
     * so a `^/(a+)+$`-style pattern anchored past a literal "/" needs
     * that prefix or its `^` anchor fails the match before backtracking
     * into the dangerous group ever begins — a bare probe would then
     * wrongly look safe.
     *
     * @return list<string>
     */
    private static function probes(): array
    {
        $shapes = [
            str_repeat('a', self::PROBE_LENGTH).'!',
            str_repeat('ab', self::PROBE_LENGTH).'!',
        ];

        return array_merge($shapes, array_map(static fn (string $shape): string => '/'.$shape, $shapes));
    }
}
