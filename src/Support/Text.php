<?php

declare(strict_types=1);

namespace Duxbo\Seo\Support;

use Normalizer;

/**
 * Text handling shared by the resolver and, later, the content analyser.
 *
 * The normalisation here is not cosmetic. Vietnamese text arrives in two
 * different Unicode compositions that look identical on screen: "tiếng" can be
 * a precomposed ế (U+1EBF) or an e with two combining marks. Comparing them
 * byte-for-byte fails, so a focus keyword typed in one editor silently stops
 * matching content pasted from another.
 */
final class Text
{
    /**
     * Normalise to NFC — precomposed characters.
     *
     * Uses ext-intl when present. The fallback covers Vietnamese and Latin-1
     * combining sequences, which is the range that actually matters here; other
     * scripts pass through unchanged rather than being mangled.
     */
    public static function normalize(string $value): string
    {
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_C);

            if (is_string($normalized)) {
                return $normalized;
            }
        }

        return self::composeFallback($value);
    }

    /**
     * Plain text from HTML, with entities decoded and whitespace collapsed.
     */
    public static function plain(string $value): string
    {
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::collapse($value);
    }

    public static function collapse(string $value): string
    {
        // \x{00A0} is a non-breaking space; CMS editors emit them constantly
        // and they are not matched by \s in every PCRE build.
        $collapsed = preg_replace('/[\s\x{00A0}]+/u', ' ', $value);

        return trim($collapsed ?? $value);
    }

    /**
     * Lowercase in a way that respects Vietnamese diacritics.
     */
    public static function lower(string $value): string
    {
        return mb_strtolower(self::normalize($value), 'UTF-8');
    }

    /**
     * Whether this text is written in a script where whitespace marks word
     * boundaries — true for Latin and Vietnamese, false for Chinese,
     * Japanese and Thai, which run letters together with no separator at
     * all. Splitting on whitespace against one of those scripts collapses
     * an entire article into a handful of "words" (a run of punctuation, a
     * stray Latin brand name), which is what {@see \Duxbo\Seo\Analysis\Tokenizer}
     * uses this to avoid.
     *
     * Decided by which kind of letter is more common in the text, not merely
     * present — a Vietnamese article quoting a Chinese proper noun once must
     * not flip the whole count to character-based.
     */
    public static function isSpaceDelimitedScript(string $value): bool
    {
        $letters = preg_match_all('/\p{L}/u', $value);

        if ($letters === false || $letters === 0) {
            return true;
        }

        $noSpaceScript = preg_match_all('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Thai}]/u', $value);
        $noSpaceScript = $noSpaceScript === false ? 0 : $noSpaceScript;

        return ($noSpaceScript / $letters) < 0.5;
    }

    /**
     * Strip Vietnamese diacritics, for accent-insensitive comparison.
     *
     * Many people search without diacritics, so a keyword check that only
     * matches accented text under-reports.
     */
    public static function stripDiacritics(string $value): string
    {
        return strtr(self::lower($value), self::FOLD);
    }

    /**
     * Compose the combining sequences that appear in Vietnamese text.
     *
     * Only the decomposed forms are handled; already-composed input is
     * returned untouched, which is the common case.
     */
    private static function composeFallback(string $value): string
    {
        static $map = null;

        if ($map === null) {
            $map = [];

            foreach (self::FOLD as $composed => $base) {
                // Rebuild the decomposed spelling: base letter plus the
                // combining mark(s) that produce this character.
                $decomposed = self::decomposeKnown($composed);

                if ($decomposed !== null && $decomposed !== $composed) {
                    $map[$decomposed] = $composed;
                }
            }
        }

        return $map === [] ? $value : strtr($value, $map);
    }

    private static function decomposeKnown(string $composed): ?string
    {
        if (! class_exists(Normalizer::class)) {
            return null;
        }

        $decomposed = Normalizer::normalize($composed, Normalizer::FORM_D);

        return is_string($decomposed) ? $decomposed : null;
    }

    /**
     * Vietnamese letters mapped to their unaccented base.
     */
    private const FOLD = [
        'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
        'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
        'đ' => 'd',
    ];
}
