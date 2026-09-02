<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Unit;

use Duxbo\Seo\Support\PixelWidth;
use PHPUnit\Framework\TestCase;

final class PixelWidthTest extends TestCase
{
    public function test_identical_character_counts_have_very_different_widths(): void
    {
        // The entire reason titles are not measured in characters.
        $this->assertGreaterThan(
            PixelWidth::of('iiiii') * 3,
            PixelWidth::of('WWWWW'),
        );
    }

    public function test_vietnamese_diacritics_do_not_add_width(): void
    {
        // "tieng" and "tiếng" occupy the same space on screen, though strlen
        // reports 5 and 8 bytes.
        $this->assertSame(PixelWidth::of('tieng'), PixelWidth::of('tiếng'));
        $this->assertSame(PixelWidth::of('duong'), PixelWidth::of('đường'));
    }

    public function test_a_short_title_is_left_alone(): void
    {
        $this->assertSame('Ngắn', PixelWidth::truncate('Ngắn', 580));
    }

    public function test_truncation_cuts_at_a_word_boundary(): void
    {
        $result = PixelWidth::truncate('Hướng dẫn tối ưu SEO cho website', 150);

        $this->assertStringEndsWith('…', $result);
        // Cut between words, so the character before the ellipsis is not a space.
        $this->assertStringNotContainsString(' …', $result);
    }

    public function test_the_ellipsis_is_counted_so_the_result_really_fits(): void
    {
        $result = PixelWidth::truncate('Hướng dẫn tối ưu SEO cho website Laravel', 200);

        $this->assertTrue(PixelWidth::fits($result, 200));
    }

    public function test_an_impossible_budget_yields_just_the_ellipsis(): void
    {
        $this->assertSame('…', PixelWidth::truncate('Bất kỳ', 2));
    }
}
