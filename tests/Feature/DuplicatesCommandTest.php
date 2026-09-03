<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class DuplicatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_a_class_that_does_not_exist(): void
    {
        $this->artisan('seo:duplicates', ['model' => 'App\\Models\\DoesNotExist'])
            ->expectsOutputToContain('No model class')
            ->assertFailed();
    }

    public function test_it_reports_no_duplicates_for_distinct_titles(): void
    {
        Post::query()->create(['name' => 'Bài một', 'slug' => 'a']);
        Post::query()->create(['name' => 'Bài hai', 'slug' => 'b']);

        $this->artisan('seo:duplicates', ['model' => Post::class])
            ->expectsOutputToContain('No duplicate resolved titles')
            ->assertSuccessful();
    }

    public function test_it_catches_a_duplicate_that_only_exists_through_the_fallback_chain(): void
    {
        // Neither post has a stored seo_meta row at all — both resolve their
        // title through the plain model-attribute mapping (name => title).
        // The live per-save check would miss this entirely, since it only
        // compares stored strings; this command resolves both and catches it.
        Post::query()->create(['name' => 'Hướng dẫn SEO', 'slug' => 'a']);
        Post::query()->create(['name' => 'Hướng dẫn SEO', 'slug' => 'b']);

        $this->artisan('seo:duplicates', ['model' => Post::class])
            ->expectsOutputToContain('1 duplicate resolved title')
            ->expectsOutputToContain('Hướng dẫn SEO')
            ->assertSuccessful();
    }

    public function test_the_comparison_is_case_insensitive(): void
    {
        Post::query()->create(['name' => 'Hướng Dẫn SEO', 'slug' => 'a']);
        Post::query()->create(['name' => 'hướng dẫn seo', 'slug' => 'b']);

        $this->artisan('seo:duplicates', ['model' => Post::class])
            ->expectsOutputToContain('1 duplicate resolved title')
            ->assertSuccessful();
    }

    public function test_the_description_field_can_be_checked_instead(): void
    {
        Post::query()->create(['name' => 'A', 'slug' => 'a', 'excerpt' => 'Trùng mô tả']);
        Post::query()->create(['name' => 'B', 'slug' => 'b', 'excerpt' => 'Trùng mô tả']);

        $this->artisan('seo:duplicates', ['model' => Post::class, '--field' => 'description'])
            ->expectsOutputToContain('1 duplicate resolved description')
            ->assertSuccessful();
    }
}
