<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Locale\AlternateLocaleResolver;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\Fixtures\TranslatedPost;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The fix for hreflang links pointing at translations that do not exist.
 *
 * Before this, every formatter and the sitemap assumed a record existed in
 * every globally supported locale and emitted an hreflang alternate for each
 * one regardless — which is how a Vietnamese-only page ended up with
 * `hreflang="en"` pointing at a URL that 404s.
 */
final class AlternateLocalesTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.locales.supported', ['vi', 'en']);
    }

    public function test_a_model_implementing_the_contract_is_trusted_outright(): void
    {
        $post = TranslatedPost::query()->create(['name' => 'X', 'slug' => 'x']);

        $locales = $this->resolver()->resolve($post);

        $this->assertSame(['vi', 'en'], $locales);
    }

    public function test_the_declared_list_is_still_intersected_with_what_the_site_supports(): void
    {
        TranslatedPost::$alternateLocales = ['vi', 'en', 'fr'];

        $post = TranslatedPost::query()->create(['name' => 'X', 'slug' => 'x']);

        // 'fr' is not in seo.locales.supported for this test, so it must not
        // resurface just because the model's own data was never updated.
        $this->assertSame(['vi', 'en'], $this->resolver()->resolve($post));

        TranslatedPost::$alternateLocales = ['vi', 'en'];
    }

    public function test_without_the_contract_only_stored_rows_count_as_evidence(): void
    {
        $post = $this->makePost();
        $post->saveSeo(['title' => 'English'], 'en');

        // 'vi' has never been stored, so it is not assumed to exist.
        $this->assertSame(['en'], $this->resolver()->resolve($post));
    }

    public function test_the_current_locale_counts_without_needing_a_stored_row(): void
    {
        $post = $this->makePost();
        $post->saveSeo(['title' => 'English'], 'en');

        // The page renders in 'vi' right now — that alone is evidence enough
        // for it to be included, on top of the stored 'en' row.
        $result = $this->resolver()->resolve($post, 'vi');

        $this->assertEqualsCanonicalizing(['en', 'vi'], $result);
    }

    public function test_a_url_with_no_model_resolves_to_no_alternates(): void
    {
        // There is no record to check evidence against, so nothing is
        // guessed — a static context is handled by the formatters falling
        // back to the site-wide locale list instead, not by this resolver.
        $this->assertSame([], $this->resolver()->resolve(null));
    }

    public function test_html_output_uses_the_declared_list_for_a_translated_model(): void
    {
        $post = TranslatedPost::query()->create(['name' => 'Bài mẫu', 'slug' => 'bai-mau']);

        $html = (string) $post->seoTags();

        $this->assertStringContainsString('hreflang="vi"', $html);
        $this->assertStringContainsString('hreflang="en"', $html);
    }

    private function resolver(): AlternateLocaleResolver
    {
        return app(AlternateLocaleResolver::class);
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
