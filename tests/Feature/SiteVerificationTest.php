<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Facades\Seo;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Search console verification codes are site-wide, not per-record — unlike
 * everything else a formatter renders, so these tests only check that a
 * configured code shows up on an ordinary page, not that it varies with one.
 */
final class SiteVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_is_emitted_when_none_are_configured(): void
    {
        $html = (string) $this->makePost()->seoTags();

        $this->assertStringNotContainsString('site-verification', $html);
    }

    public function test_html_renders_the_configured_codes(): void
    {
        config([
            'seo.verification.google' => 'abc123',
            'seo.verification.bing' => 'def456',
        ]);

        $html = (string) $this->makePost()->seoTags();

        $this->assertStringContainsString('<meta name="google-site-verification" content="abc123">', $html);
        $this->assertStringContainsString('<meta name="msvalidate.1" content="def456">', $html);
    }

    public function test_head_formatter_includes_them_in_its_meta_array(): void
    {
        config(['seo.verification.yandex' => 'xyz789']);

        $post = $this->makePost();

        // 'nuxt' and 'vue' are both HeadFormatter under a different name —
        // there is no bare 'head' registered, only the two front ends that
        // actually consume the Unhead-shaped payload it produces.
        /** @var array<string, mixed> $output */
        $output = Seo::format('nuxt', $post->seoContext());

        $found = collect($output['meta'])->first(
            fn (array $tag): bool => ($tag['name'] ?? null) === 'yandex-verification',
        );

        $this->assertNotNull($found);
        $this->assertSame('xyz789', $found['content']);
    }

    public function test_next_formatter_maps_google_and_yandex_to_their_named_slots_and_the_rest_under_other(): void
    {
        config([
            'seo.verification.google' => 'g-code',
            'seo.verification.yandex' => 'y-code',
            'seo.verification.pinterest' => 'p-code',
        ]);

        $post = $this->makePost();

        /** @var array<string, mixed> $output */
        $output = Seo::format('next', $post->seoContext());

        $this->assertSame('g-code', $output['verification']['google']);
        $this->assertSame('y-code', $output['verification']['yandex']);
        $this->assertSame('p-code', $output['verification']['other']['p:domain_verify']);
    }

    public function test_next_formatter_omits_verification_entirely_when_none_are_configured(): void
    {
        $post = $this->makePost();

        /** @var array<string, mixed> $output */
        $output = Seo::format('next', $post->seoContext());

        $this->assertArrayNotHasKey('verification', $output);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePost(array $attributes = []): Post
    {
        return Post::query()->create($attributes + [
            'name' => 'Bài viết mẫu',
            'slug' => 'bai-viet-mau',
        ]);
    }
}
