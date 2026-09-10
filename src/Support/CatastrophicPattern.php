<?php

declare(strict_types=1);

namespace Duxbo\Seo\Support;

/**
 * Detects the PCRE shape most likely to hang a request on crafted input: a
 * quantified group nested inside another quantifier. Shared by
 * {@see \Duxbo\Seo\Redirects\RedirectGuard} (a stored redirect rule) and
 * {@see \Duxbo\Seo\Settings\Validators\RegexListSettingValidator}
 * (`seo.not_found.exclude`) — the same risk applies to both: a pattern an
 * admin can save that then runs against every request afterward.
 */
final class CatastrophicPattern
{
    private const SIGNATURE = '/(\([^)]*[+*][^)]*\)|\[[^\]]*\])\s*[+*]{1,2}/';

    public static function detected(string $pattern): bool
    {
        return preg_match(self::SIGNATURE, $pattern) === 1;
    }
}
