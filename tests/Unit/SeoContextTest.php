<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Unit;

use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Data\SeoData;
use PHPUnit\Framework\TestCase;

final class SeoContextTest extends TestCase
{
    public function test_a_bare_context_starts_with_empty_data_rather_than_null(): void
    {
        $context = SeoContext::forUrl('https://site.vn/bai-viet');

        $this->assertTrue($context->data->isEmpty());
        $this->assertFalse($context->hasModel());
    }

    public function test_it_takes_url_and_attributes_from_the_model(): void
    {
        $context = SeoContext::for($this->model());

        $this->assertSame('https://site.vn/bai-viet', $context->url);
        $this->assertTrue($context->hasModel());
        $this->assertSame('Bài viết mẫu', $context->attribute('name'));
        $this->assertNull($context->attribute('missing'));
    }

    public function test_fill_missing_leaves_a_decided_value_alone(): void
    {
        $context = SeoContext::forUrl('https://site.vn/')
            ->withData(new SeoData(title: 'Đã quyết'))
            ->fillMissing(new SeoData(title: 'Bỏ qua', description: 'Nhận'));

        $this->assertSame('Đã quyết', $context->data->title);
        $this->assertSame('Nhận', $context->data->description);
    }

    public function test_the_bag_carries_state_between_stages_without_mutating(): void
    {
        $first = SeoContext::forUrl('https://site.vn/');
        $second = $first->put('resolved_by', 'template');

        $this->assertNull($first->get('resolved_by'));
        $this->assertSame('template', $second->get('resolved_by'));
        $this->assertSame('fallback', $first->get('resolved_by', 'fallback'));
    }

    private function model(): Seoable
    {
        return new class implements Seoable
        {
            public function seoType(): string
            {
                return 'post';
            }

            public function seoKey(): int|string
            {
                return 1;
            }

            public function seoUrl(): string
            {
                return 'https://site.vn/bai-viet';
            }

            public function seoDefaults(): array
            {
                return ['title' => 'Bài viết mẫu'];
            }

            public function seoAttributes(): array
            {
                return ['name' => 'Bài viết mẫu'];
            }
        };
    }
}
