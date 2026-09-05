<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis;

use Duxbo\Seo\Support\Text;

/**
 * "How much text is here" for whichever checks need a length or a density
 * denominator, dispatched by script rather than always assuming one.
 *
 * {@see Vietnamese::syllableCount()} splits on whitespace, which is exactly
 * right for Vietnamese and a reasonable proxy for English — both actually
 * separate words with spaces. Chinese, Japanese and Thai do not: a full
 * article in one of those scripts has almost no whitespace at all, so the
 * same split collapses it to a handful of tokens (a run of digits, a stray
 * Latin word) and every length-based check built on it reports "too short"
 * regardless of how much was actually written.
 */
final class Tokenizer
{
    /**
     * A length figure comparable across scripts: syllables (whitespace
     * tokens) for a space-delimited script, letters for one that runs words
     * together.
     */
    public static function count(string $text): int
    {
        if (Text::isSpaceDelimitedScript($text)) {
            return Vietnamese::syllableCount($text);
        }

        $normalized = Text::normalize($text);
        $letters = preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? '';

        return mb_strlen($letters, 'UTF-8');
    }
}
