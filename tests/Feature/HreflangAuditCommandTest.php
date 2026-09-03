<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\Fixtures\TranslatedPost;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class HreflangAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.locales.supported', ['vi', 'en']);
    }

    public function test_rejects_an_unknown_model_class(): void
    {
        $this->artisan('seo:hreflang', ['model' => 'Not\A\Real\Class'])
            ->expectsOutputToContain('No model class')
            ->assertFailed();
    }

    public function test_rejects_a_model_that_does_not_implement_seoable(): void
    {
        $this->artisan('seo:hreflang', ['model' => \stdClass::class])
            ->assertFailed();
    }

    public function test_records_with_fewer_than_two_alternates_are_skipped_without_flagging(): void
    {
        $this->makePost();

        $this->artisan('seo:hreflang', ['model' => Post::class])
            ->expectsOutputToContain('Checked 0 record(s)')
            ->expectsOutputToContain('No colliding hreflang URLs found.')
            ->assertSuccessful();
    }

    public function test_the_default_locale_segment_convention_produces_no_collision(): void
    {
        TranslatedPost::query()->create(['name' => 'Bài mẫu', 'slug' => 'bai-mau']);

        // TranslatedPost::seoUrl() returns the same fixed URL regardless of
        // locale — the default alternate() convention still gives 'vi' and
        // 'en' distinct URLs by inserting a locale segment into the path.
        $this->artisan('seo:hreflang', ['model' => TranslatedPost::class])
            ->expectsOutputToContain('Checked 1 record(s)')
            ->expectsOutputToContain('No colliding hreflang URLs found.')
            ->assertSuccessful();
    }

    public function test_flags_a_record_whose_alternate_resolver_ignores_the_locale(): void
    {
        // A resolver that forgets to use its own $locale argument is exactly
        // the misconfiguration this command exists to catch — every locale
        // then resolves to the same URL.
        config(['seo.locales.alternate_url' => fn (string $url, string $locale): string => $url]);

        TranslatedPost::query()->create(['name' => 'Bài mẫu', 'slug' => 'bai-mau']);

        $this->artisan('seo:hreflang', ['model' => TranslatedPost::class])
            ->expectsOutputToContain('hreflang="vi" and hreflang="en" both resolve to')
            ->expectsOutputToContain('1 colliding pair(s) found')
            ->assertSuccessful();
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
