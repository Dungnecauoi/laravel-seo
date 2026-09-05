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

    public function test_vietnamese_and_english_are_space_delimited(): void
    {
        $this->assertTrue(Text::isSpaceDelimitedScript('Tiếng Việt rất hay.'));
        $this->assertTrue(Text::isSpaceDelimitedScript('The quick brown fox.'));
    }

    public function test_chinese_japanese_and_thai_are_not_space_delimited(): void
    {
        $this->assertFalse(Text::isSpaceDelimitedScript('这是一篇关于搜索引擎优化的文章'));
        $this->assertFalse(Text::isSpaceDelimitedScript('これは日本語のテキストです'));
        $this->assertFalse(Text::isSpaceDelimitedScript('นี่คือข้อความภาษาไทย'));
    }

    public function test_a_stray_foreign_word_does_not_flip_the_dominant_script(): void
    {
        // One quoted Chinese proper noun in an otherwise Vietnamese article
        // must not switch the whole piece to character counting.
        $this->assertTrue(Text::isSpaceDelimitedScript(
            'Bài báo nhắc đến thành phố 北京 trong đoạn mở đầu, rồi tiếp tục bằng tiếng Việt.',
        ));
    }

    public function test_text_with_no_letters_at_all_defaults_to_space_delimited(): void
    {
        $this->assertTrue(Text::isSpaceDelimitedScript('123 456 !!! ***'));
    }
}
