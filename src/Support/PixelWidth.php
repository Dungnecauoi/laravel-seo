<?php

declare(strict_types=1);

namespace Duxbo\Seo\Support;

/**
 * Estimates rendered width of a title in pixels.
 *
 * Google truncates search result titles by rendered width, not character count:
 * "iiiii" and "WWWWW" are the same length by strlen and three times apart on
 * screen. Counting characters therefore produces titles that are either cut off
 * or needlessly short.
 *
 * This matters more for Vietnamese than for English. Diacritics add bytes
 * without adding width, so `strlen('tiếng')` is 8 while the glyphs occupy about
 * the same space as five plain letters. Widths are measured against the base
 * letter after diacritics are stripped.
 *
 * Figures approximate Arial at 20px, which is what desktop results render in.
 */
final class PixelWidth
{
    /**
     * Width in pixels, keyed by character.
     *
     * @var array<string, int>
     */
    private const WIDTHS = [
        ' ' => 5,
        'i' => 4, 'j' => 4, 'l' => 4, 'I' => 5, '.' => 5, ',' => 5, ':' => 5,
        ';' => 5, '!' => 5, '|' => 5, "'" => 4, '`' => 6, '(' => 6, ')' => 6,
        '[' => 5, ']' => 5, '{' => 6, '}' => 6, '/' => 5, '\\' => 5, '-' => 6,
        'f' => 5, 't' => 5, 'r' => 6, '"' => 7, '*' => 7,
        'c' => 9, 'e' => 10, 's' => 9, 'z' => 9, 'v' => 9, 'x' => 9, 'y' => 9,
        'a' => 10, 'b' => 10, 'd' => 10, 'g' => 10, 'h' => 10, 'k' => 9,
        'n' => 10, 'o' => 10, 'p' => 10, 'q' => 10, 'u' => 10,
        'm' => 16, 'w' => 14,
        'J' => 8, 'L' => 11, 'E' => 13, 'F' => 12, 'P' => 13, 'S' => 13,
        'A' => 13, 'B' => 13, 'C' => 14, 'D' => 14, 'G' => 15, 'H' => 14,
        'K' => 13, 'N' => 14, 'O' => 15, 'Q' => 15, 'R' => 14, 'T' => 12,
        'U' => 14, 'V' => 13, 'X' => 13, 'Y' => 13, 'Z' => 12,
        'M' => 17, 'W' => 19,
        '0' => 11, '1' => 11, '2' => 11, '3' => 11, '4' => 11,
        '5' => 11, '6' => 11, '7' => 11, '8' => 11, '9' => 11,
    ];

    private const DEFAULT_WIDTH = 10;

    /**
     * Vietnamese and other Latin diacritics mapped to their base letter, whose
     * width they share.
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

    public static function of(string $text): int
    {
        $width = 0;

        foreach (self::characters($text) as $character) {
            $width += self::widthOf($character);
        }

        return $width;
    }

    public static function fits(string $text, int $maxPixels): bool
    {
        return self::of($text) <= $maxPixels;
    }

    /**
     * Trim to fit, cutting at a word boundary and appending an ellipsis.
     *
     * The ellipsis is measured too, so the result genuinely fits rather than
     * overflowing by exactly the width of the ellipsis.
     */
    public static function truncate(string $text, int $maxPixels, string $ellipsis = '…'): string
    {
        if (self::fits($text, $maxPixels)) {
            return $text;
        }

        $budget = $maxPixels - self::of($ellipsis);

        if ($budget <= 0) {
            return $ellipsis;
        }

        $width = 0;
        $kept = '';
        $lastBoundary = null;

        foreach (self::characters($text) as $character) {
            $next = $width + self::widthOf($character);

            if ($next > $budget) {
                break;
            }

            $width = $next;
            $kept .= $character;

            if ($character === ' ') {
                $lastBoundary = rtrim($kept);
            }
        }

        $result = $lastBoundary !== null && $lastBoundary !== '' ? $lastBoundary : rtrim($kept);

        return $result === '' ? $ellipsis : $result.$ellipsis;
    }

    private static function widthOf(string $character): int
    {
        $folded = self::FOLD[mb_strtolower($character, 'UTF-8')] ?? null;

        if ($folded !== null) {
            // Capitals keep their own width once folded to the base letter.
            $character = mb_strtolower($character, 'UTF-8') === $character
                ? $folded
                : mb_strtoupper($folded, 'UTF-8');
        }

        return self::WIDTHS[$character] ?? self::DEFAULT_WIDTH;
    }

    /**
     * @return list<string>
     */
    private static function characters(string $text): array
    {
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $characters === false ? [] : $characters;
    }
}
