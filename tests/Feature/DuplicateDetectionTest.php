<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/**
 * The live half: comparing stored title/description strings at save time,
 * cheap enough for a request a save is waiting on.
 */
final class DuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.api.enabled', true);
        $app['config']->set('seo.api.models', ['post']);
    }

    public function test_no_warning_when_the_title_is_unique(): void
    {
        $a = $this->makePost(['slug' => 'a']);
        $a->saveSeo(['title' => 'Duy nhất']);

        $matches = app(MetadataRepository::class)->duplicateTitles($a, 'Duy nhất');

        $this->assertSame([], $matches);
    }

    public function test_a_match_is_reported_with_the_other_records_identity(): void
    {
        $a = $this->makePost(['slug' => 'a']);
        $b = $this->makePost(['slug' => 'b']);

        $a->saveSeo(['title' => 'Cùng tiêu đề']);
        $b->saveSeo(['title' => 'Cùng tiêu đề']);

        $matches = app(MetadataRepository::class)->duplicateTitles($b, 'Cùng tiêu đề');

        $this->assertCount(1, $matches);
        $this->assertSame((string) $a->getKey(), $matches[0]->seoKey);
        $this->assertSame($a->seoType(), $matches[0]->seoType);
    }

    public function test_a_record_is_never_reported_as_its_own_duplicate(): void
    {
        $a = $this->makePost();
        $a->saveSeo(['title' => 'Tiêu đề']);

        // Checking the exact value the record itself already has must not
        // find itself.
        $matches = app(MetadataRepository::class)->duplicateTitles($a, 'Tiêu đề');

        $this->assertSame([], $matches);
    }

    public function test_locales_are_compared_separately(): void
    {
        $a = $this->makePost(['slug' => 'a']);
        $b = $this->makePost(['slug' => 'b']);

        $a->saveSeo(['title' => 'Cùng'], 'vi');
        $b->saveSeo(['title' => 'Cùng'], 'en');

        // A Vietnamese title and an English title matching by coincidence do
        // not compete in the same search results.
        $this->assertSame([], app(MetadataRepository::class)->duplicateTitles($b, 'Cùng', 'en'));
    }

    public function test_descriptions_are_checked_the_same_way(): void
    {
        $a = $this->makePost(['slug' => 'a']);
        $b = $this->makePost(['slug' => 'b']);

        $a->saveSeo(['description' => 'Mô tả trùng lặp']);
        $b->saveSeo(['description' => 'Mô tả trùng lặp']);

        $matches = app(MetadataRepository::class)->duplicateDescriptions($b, 'Mô tả trùng lặp');

        $this->assertCount(1, $matches);
    }

    public function test_saving_through_the_api_surfaces_the_warning(): void
    {
        \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap(['post' => Post::class]);
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => true);

        $a = $this->makePost(['slug' => 'a']);
        $a->saveSeo(['title' => 'Tiêu đề trùng']);

        $b = $this->makePost(['slug' => 'b']);

        $this->putJson("/api/seo/v1/meta/post/{$b->getKey()}", ['title' => 'Tiêu đề trùng'])
            ->assertOk()
            ->assertJsonPath('warnings.duplicate_title.0.id', (string) $a->getKey());

        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([], false);
        \Illuminate\Database\Eloquent\Relations\Relation::requireMorphMap(false);
    }

    public function test_saving_a_unique_title_through_the_api_has_no_warnings(): void
    {
        \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap(['post' => Post::class]);
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => true);

        $post = $this->makePost();

        $this->putJson("/api/seo/v1/meta/post/{$post->getKey()}", ['title' => 'Không ai khác dùng'])
            ->assertOk()
            ->assertJsonPath('warnings', []);

        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([], false);
        \Illuminate\Database\Eloquent\Relations\Relation::requireMorphMap(false);
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
