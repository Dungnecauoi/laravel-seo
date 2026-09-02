<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Unit;

use Duxbo\Seo\Support\Text;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function test_the_two_unicode_spellings_of_a_vietnamese_word_compare_equal(): void
    {
        // Identical on screen, different bytes: precomposed ế (U+1EBF) versus
        // e + combining circumflex + combining acute. Without normalising, a
        // focus keyword typed in one editor stops matching content pasted from
        // another.
        $precomposed = "ti\u{1EBF}ng";
        $decomposed = "tie\u{0302}\u{0301}ng";

        $this->assertNotSame($precomposed, $decomposed);
        $this->assertSame(
            Text::normalize($precomposed),
            Text::normalize($decomposed),
        );
    }

    public function test_it_strips_diacritics_for_accent_insensitive_matching(): void
    {
        $this->assertSame('tieng viet', Text::stripDiacritics('Tiếng Việt'));
        $this->assertSame('duong', Text::stripDiacritics('Đường'));
    }

    public function test_plain_removes_markup_and_decodes_entities(): void
    {
        $this->assertSame(
            'Mô tả "có" thẻ',
            Text::plain('<p>Mô tả <strong>&quot;có&quot;</strong> thẻ</p>'),
        );
    }

    public function test_collapse_handles_the_non_breaking_spaces_editors_emit(): void
    {
        $this->assertSame('a b c', Text::collapse("a\u{00A0}\u{00A0}b \n c"));
    }

    public function test_lowercasing_keeps_diacritics_intact(): void
    {
        $this->assertSame('tiếng việt', Text::lower('Tiếng Việt'));
    }
}
