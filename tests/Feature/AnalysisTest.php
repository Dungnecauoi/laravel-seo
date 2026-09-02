<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Analysis\Analyzer;
use Duxbo\Seo\Enums\CheckStatus;
use Duxbo\Seo\Tests\TestCase;

final class AnalysisTest extends TestCase
{
    public function test_a_well_optimised_vietnamese_page_scores_highly(): void
    {
        $report = $this->analyze(
            content: $this->article(),
            keyword: 'tối ưu SEO',
            title: 'Hướng dẫn tối ưu SEO cho website',
            description: 'Bài viết hướng dẫn tối ưu SEO cho website Laravel, từ thẻ meta tới dữ liệu có cấu trúc và tốc độ tải trang.',
            url: 'https://trangcuatoi.vn/huong-dan-toi-uu-seo',
            locale: 'vi',
        );

        $this->assertGreaterThan(70, $report->score);
    }

    public function test_english_only_checks_sit_out_on_vietnamese(): void
    {
        $report = $this->analyze($this->article(), 'tối ưu SEO', locale: 'vi');

        $ids = array_map(static fn ($r): string => $r->id, $report->results);

        // Flesch counts syllables the way English spells them and produces a
        // confident number that means nothing here.
        $this->assertNotContains('en-flesch', $ids);
        $this->assertContains('vi-readability', $ids);
    }

    public function test_vietnamese_checks_sit_out_on_english(): void
    {
        $report = $this->analyze($this->article(), 'seo', locale: 'en');

        $ids = array_map(static fn ($r): string => $r->id, $report->results);

        $this->assertContains('en-flesch', $ids);
        $this->assertNotContains('vi-readability', $ids);
    }

    public function test_a_keyword_matches_across_unicode_spellings(): void
    {
        // The content uses the precomposed form, the keyword the decomposed
        // one. They look identical and would not compare equal unnormalised.
        $report = $this->analyze(
            content: "<p>Bài viết về ti\u{1EBF}ng Việt và cách viết.</p>",
            keyword: "ti\u{0065}\u{0302}\u{0301}ng Việt",
            title: "Học ti\u{1EBF}ng Việt",
            locale: 'vi',
        );

        $this->assertSame(CheckStatus::Pass, $this->checkResult($report, 'keyword-in-title')->status);
    }

    public function test_a_missing_keyword_in_the_title_fails(): void
    {
        $report = $this->analyze($this->article(), 'món ăn ngon', title: 'Hướng dẫn lập trình');

        $this->assertSame(CheckStatus::Fail, $this->checkResult($report, 'keyword-in-title')->status);
    }

    public function test_keyword_stuffing_fails(): void
    {
        $stuffed = '<p>'.str_repeat('tối ưu SEO là tối ưu SEO. ', 40).'</p>';

        $report = $this->analyze($stuffed, 'tối ưu SEO', locale: 'vi');

        $this->assertSame(CheckStatus::Fail, $this->checkResult($report, 'keyword-density')->status);
    }

    public function test_density_counts_syllables_occupied_not_occurrences(): void
    {
        // A three-syllable keyword occupies three times the space of a one-word
        // one, so counting occurrences would under-report it.
        $report = $this->analyze($this->article(), 'tối ưu SEO', locale: 'vi');
        $density = $this->checkResult($report, 'keyword-density')->context['density'];

        $occurrences = $this->checkResult($report, 'keyword-density')->context['occurrences'];

        $this->assertGreaterThan(0, $occurrences);
        $this->assertGreaterThan(0, $density);
    }

    public function test_a_missing_alt_attribute_is_reported(): void
    {
        $report = $this->analyze('<p>Xin chào</p><img src="/a.jpg"><img src="/b.jpg" alt="Có mô tả">');

        $result = $this->checkResult($report, 'images-have-alt');

        $this->assertSame(CheckStatus::Fail, $result->status);
        $this->assertSame(1, $result->context['missing']);
        $this->assertSame(2, $result->context['total']);
    }

    public function test_internal_and_external_links_are_told_apart(): void
    {
        $report = $this->analyze(
            '<p><a href="/trang-trong">Nội bộ</a> <a href="https://example.com">Ngoài</a></p>',
        );

        $this->assertSame(CheckStatus::Pass, $this->checkResult($report, 'internal-links')->status);
        $this->assertSame(CheckStatus::Pass, $this->checkResult($report, 'external-links')->status);
    }

    public function test_more_than_one_h1_is_reported(): void
    {
        $report = $this->analyze('<h1>Một</h1><h1>Hai</h1><p>Nội dung.</p>');

        $result = $this->checkResult($report, 'single-h1');

        $this->assertSame(CheckStatus::Warning, $result->status);
        $this->assertSame(2, $result->context['count']);
    }

    public function test_long_vietnamese_sentences_lower_readability(): void
    {
        $long = '<p>'.str_repeat('Đây là một câu rất dài được viết ra nhằm mục đích kiểm tra khả năng đọc hiểu của người dùng khi họ gặp phải những câu văn kéo dài liên tục không có dấu chấm câu nào cả và tiếp tục mãi. ', 5).'</p>';

        $report = $this->analyze($long, locale: 'vi');
        $result = $this->checkResult($report, 'vi-readability');

        $this->assertContains($result->status, [CheckStatus::Warning, CheckStatus::Fail]);
        $this->assertGreaterThan(20, $result->context['average']);
    }

    public function test_a_removed_check_stops_running(): void
    {
        app(Analyzer::class)->remove('keyword-in-title');

        $report = $this->analyze($this->article(), 'không có ở đâu');
        $ids = array_map(static fn ($r): string => $r->id, $report->results);

        $this->assertNotContains('keyword-in-title', $ids);
    }

    public function test_a_check_that_throws_does_not_take_the_report_down(): void
    {
        config(['app.debug' => false]);

        app(Analyzer::class)->register(new class extends \Duxbo\Seo\Analysis\Checks\Check
        {
            public function id(): string
            {
                return 'explodes';
            }

            public function run(\Duxbo\Seo\Data\AnalysisContext $context): \Duxbo\Seo\Data\CheckResult
            {
                throw new \RuntimeException('boom');
            }
        });

        $report = $this->analyze($this->article(), 'tối ưu SEO', locale: 'vi');

        // A panel showing nothing because one third-party rule hit an edge case
        // is worse than one honest gap.
        $this->assertSame(CheckStatus::Skipped, $this->checkResult($report, 'explodes')->status);
        $this->assertGreaterThan(0, $report->score);
    }

    public function test_html_is_parsed_rather_than_pattern_matched(): void
    {
        // An attribute containing a bracket breaks every regex-based extractor.
        $report = $this->analyze('<p><a href="/x?a=[1]" title="a>b">Nội bộ</a></p>');

        $this->assertSame(CheckStatus::Pass, $this->checkResult($report, 'internal-links')->status);
    }

    private function analyze(
        string $content,
        ?string $keyword = null,
        ?string $title = null,
        ?string $description = null,
        ?string $url = null,
        ?string $locale = null,
    ): \Duxbo\Seo\Data\AnalysisReport {
        return app(Analyzer::class)->analyze($content, $keyword, $title, $description, $url, $locale);
    }

    private function checkResult(\Duxbo\Seo\Data\AnalysisReport $report, string $id): \Duxbo\Seo\Data\CheckResult
    {
        foreach ($report->results as $result) {
            if ($result->id === $id) {
                return $result;
            }
        }

        $this->fail("No result for check [{$id}].");
    }

    private function article(): string
    {
        return <<<'HTML'
        <h1>Hướng dẫn tối ưu SEO cho website</h1>
        <p>Tối ưu SEO là việc cần làm. Bài viết này nói về tối ưu SEO cho website Laravel.</p>
        <h2>Vì sao cần tối ưu SEO</h2>
        <p>Trang web có thứ hạng tốt sẽ nhận nhiều lượt truy cập hơn. Đây là lý do chính.</p>
        <p>Xem thêm <a href="/bai-viet-khac">bài viết khác</a> và tài liệu tại
        <a href="https://developers.google.com">Google</a>.</p>
        <h3>Các bước thực hiện</h3>
        <p>Đầu tiên là thẻ tiêu đề. Sau đó là mô tả. Cuối cùng là dữ liệu có cấu trúc.</p>
        <img src="/anh.jpg" alt="Minh hoạ tối ưu SEO">
        <p>Nội dung cần đủ dài để người đọc hiểu vấn đề. Viết ngắn gọn và rõ ràng.
        Mỗi câu nên nói một ý. Người đọc sẽ dễ theo dõi hơn nhiều.</p>
        HTML;
    }
}
