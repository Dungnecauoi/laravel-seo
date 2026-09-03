<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Events\SeoMetaSaved;
use Duxbo\Seo\Facades\Seo;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\Fixtures\UnroutedPost;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

/**
 * The extension point IndexNow, cache purging, or anything else a save
 * should trigger is meant to hang off — see the event's own docblock for
 * why nothing in this package listens to it itself.
 */
final class SeoMetaSavedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_fires_the_event_with_the_model_locale_and_resolved_url(): void
    {
        $post = $this->makePost();
        $captured = null;

        Event::listen(SeoMetaSaved::class, function (SeoMetaSaved $event) use (&$captured): void {
            $captured = $event;
        });

        Seo::save($post, ['title' => 'Tiêu đề mới'], 'vi');

        $this->assertNotNull($captured);
        $this->assertTrue($captured->model->is($post));
        $this->assertSame('vi', $captured->locale);
        // The locale segment comes from UrlGenerator::alternate()'s default
        // convention, applied because a locale other than the page's own
        // default was explicitly asked for.
        $this->assertSame('https://trangcuatoi.vn/vi/bai-viet/bai-viet-mau', $captured->url);
    }

    public function test_the_url_is_null_rather_than_throwing_when_it_cannot_be_resolved(): void
    {
        $post = UnroutedPost::query()->create(['name' => 'X', 'slug' => 'x']);
        $captured = null;

        Event::listen(SeoMetaSaved::class, function (SeoMetaSaved $event) use (&$captured): void {
            $captured = $event;
        });

        // Must not throw: storing what was typed in is a different concern
        // from resolving where it will be published, and the first must
        // succeed regardless of whether the second currently can.
        Seo::save($post, ['title' => 'Tiêu đề mới']);

        $this->assertNotNull($captured);
        $this->assertNull($captured->url);
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
