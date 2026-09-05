<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Unit;

use Duxbo\Seo\Analysis\Tokenizer;
use PHPUnit\Framework\TestCase;

final class TokenizerTest extends TestCase
{
    public function test_it_counts_syllables_for_a_space_delimited_script(): void
    {
        $this->assertSame(3, Tokenizer::count('Học sinh giỏi'));
    }

    public function test_it_counts_letters_for_a_script_with_no_word_spaces(): void
    {
        // Splitting this on whitespace (there is none) would yield 1 — the
        // exact defect Tokenizer exists to avoid.
        $this->assertSame(6, Tokenizer::count('搜索引擎优化'));
    }

    public function test_a_long_cjk_passage_is_not_collapsed_to_a_single_token(): void
    {
        $long = str_repeat('这是一段用于测试的中文内容', 50);

        $this->assertGreaterThan(500, Tokenizer::count($long));
    }
}
