<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis;

use Duxbo\Seo\Support\Text;

/**
 * Readability measures for Vietnamese.
 *
 * Flesch Reading Ease and every formula derived from it counts syllables the
 * way English spells them, and produces nonsense on Vietnamese. Vietnamese is
 * monosyllabic and written with a space between syllables, so counting them is
 * *easier* than in English — one space-separated token is one syllable — but
 * the thresholds are entirely different, and word boundaries do not align with
 * spaces at all ("học sinh" is one word of two syllables).
 *
 * The measures here are heuristics, not validated instruments. They are stated
 * as such in the messages so nobody treats a number as research.
 */
final class Vietnamese
{
    /**
     * Syllables — `tiếng` — in a piece of text.
     *
     * Punctuation and digits are dropped first, because a price or a date would
     * otherwise inflate the count and make prose look denser than it reads.
     */
    public static function syllableCount(string $text): int
    {
        return count(self::syllables($text));
    }

    /**
     * @return list<string>
     */
    public static function syllables(string $text): array
    {
        $normalized = Text::normalize($text);

        // Keep letters and the Vietnamese diacritics; drop everything else.
        $cleaned = preg_replace('/[^\p{L}\s]+/u', ' ', $normalized) ?? '';

        $tokens = preg_split('/\s+/u', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY);

        return $tokens === false ? [] : $tokens;
    }

    /**
     * @return list<string>
     */
    public static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?…])\s+|\n+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', $parts),
            static fn (string $part): bool => $part !== '',
        ));
    }

    /**
     * Mean syllables per sentence — the readability proxy used here.
     */
    public static function averageSentenceLength(string $text): float
    {
        $sentences = self::sentences($text);

        if ($sentences === []) {
            return 0.0;
        }

        $total = 0;

        foreach ($sentences as $sentence) {
            $total += self::syllableCount($sentence);
        }

        return $total / count($sentences);
    }

    /**
     * Sentences that run past the threshold, so a check can name them.
     *
     * @return list<string>
     */
    public static function longSentences(string $text, int $threshold = 25): array
    {
        $long = [];

        foreach (self::sentences($text) as $sentence) {
            if (self::syllableCount($sentence) > $threshold) {
                $long[] = $sentence;
            }
        }

        return $long;
    }

    /**
     * Sentences carrying a passive marker.
     *
     * `được` and `bị` before a verb mark the passive in Vietnamese. This is
     * marker-spotting rather than parsing, so it over-reports: `được` also
     * means "to receive" and appears in plenty of active sentences. Treated as
     * a hint, never a failure.
     *
     * @return list<string>
     */
    public static function passiveSentences(string $text): array
    {
        $passive = [];

        foreach (self::sentences($text) as $sentence) {
            if (preg_match('/\b(được|bị)\s+\p{L}+/u', Text::lower($sentence)) === 1) {
                $passive[] = $sentence;
            }
        }

        return $passive;
    }

    /**
     * Count occurrences of a phrase.
     *
     * Matched as a literal phrase, not by tokens: Vietnamese word segmentation
     * needs a dictionary, which is out of scope, and splitting on spaces would
     * count "học sinh" as two unrelated words.
     */
    public static function phraseCount(string $haystack, string $phrase, bool $ignoreDiacritics = false): int
    {
        $haystack = $ignoreDiacritics ? Text::stripDiacritics($haystack) : Text::lower($haystack);
        $phrase = $ignoreDiacritics ? Text::stripDiacritics($phrase) : Text::lower($phrase);

        if (trim($phrase) === '') {
            return 0;
        }

        return substr_count($haystack, $phrase);
    }
}
