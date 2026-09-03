<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Data\SeoData;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

final class StorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_locale_row_is_preferred_over_the_shared_one(): void
    {
        $post = $this->makePost();

        $this->repository()->save($post, new SeoData(title: 'Dùng chung'), null);
        $this->repository()->save($post, new SeoData(title: 'Bản tiếng Anh'), 'en');

        $this->assertSame('Bản tiếng Anh', $this->repository()->find($post, 'en')?->title);
        $this->assertSame('Dùng chung', $this->repository()->find($post, 'vi')?->title);
    }

    public function test_a_regional_locale_falls_back_to_its_base_language(): void
    {
        $post = $this->makePost();
        $this->repository()->save($post, new SeoData(title: 'English'), 'en');

        $this->assertSame('English', $this->repository()->find($post, 'en-GB')?->title);
    }

    public function test_deleting_the_shared_row_leaves_translations_alone(): void
    {
        $post = $this->makePost();
        $this->repository()->save($post, new SeoData(title: 'Dùng chung'), null);
        $this->repository()->save($post, new SeoData(title: 'English'), 'en');

        $this->repository()->delete($post, null);

        $this->assertNull($this->repository()->find($post, null));
        $this->assertSame('English', $this->repository()->find($post, 'en')?->title);
    }

    public function test_it_reports_which_locales_a_record_has(): void
    {
        $post = $this->makePost();
        $this->repository()->save($post, new SeoData(title: 'x'), 'en');
        $this->repository()->save($post, new SeoData(title: 'y'), 'vi');
        $this->repository()->save($post, new SeoData(title: 'z'), null);

        // The shared row is not a locale.
        $this->assertSame(['en', 'vi'], $this->repository()->locales($post));
    }

    public function test_robots_rules_survive_a_round_trip(): void
    {
        $post = $this->makePost();
        $post->saveSeo(['robots' => ['noindex', 'max-snippet:50']]);

        $this->assertSame('noindex, max-snippet:50', $post->fresh()->seo()->robotsLine());
    }

    public function test_a_directive_written_by_a_newer_release_is_ignored_not_fatal(): void
    {
        $post = $this->makePost();
        $post->saveSeo(['title' => 'Giữ nguyên']);

        DB::table('seo_meta')->update([
            'robots' => json_encode([['directive' => 'some-future-directive', 'value' => null]]),
        ]);

        // Dropping an unknown directive beats failing to render the page. The
        // stored robots array is empty once the unknown entry is dropped, so
        // it counts as "not decided" and the site-wide default fills in —
        // the same as if nothing had been stored at all.
        $data = $post->fresh()->seo();

        $this->assertSame('Giữ nguyên', $data->title);
        $this->assertSame('max-image-preview:large', $data->robotsLine());
    }

    public function test_eager_loading_resolves_a_page_of_records_without_a_query_each(): void
    {
        foreach (range(1, 10) as $i) {
            $post = $this->makePost(['slug' => "bai-{$i}"]);
            $post->saveSeo(['title' => "Tiêu đề {$i}"]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $posts = Post::query()->withSeo()->get();

        foreach ($posts as $post) {
            $this->assertNotNull($post->seo()->title);
        }

        // One query for the posts, one for their metadata. Without withSeo()
        // this would be eleven.
        $this->assertCount(2, DB::getQueryLog());
    }

    public function test_find_many_loads_a_whole_collection_in_one_query(): void
    {
        $posts = collect(range(1, 5))->map(function (int $i): Post {
            $post = $this->makePost(['slug' => "bai-{$i}"]);
            $post->saveSeo(['title' => "Tiêu đề {$i}"]);

            return $post;
        });

        DB::flushQueryLog();
        DB::enableQueryLog();

        $found = $this->repository()->findMany($posts);

        $this->assertCount(1, DB::getQueryLog());
        $this->assertCount(5, $found);
        $this->assertSame('Tiêu đề 3', $found->get(Post::class.':'.$posts[2]->getKey())?->title);
    }

    private function repository(): MetadataRepository
    {
        return app(MetadataRepository::class);
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
