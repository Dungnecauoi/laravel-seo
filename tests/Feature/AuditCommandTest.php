<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Audit\Audit;
use Duxbo\Seo\Audit\AuditBatch;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class AuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_an_unknown_model_class(): void
    {
        $this->artisan('seo:audit', ['model' => 'Not\A\Real\Class'])
            ->expectsOutputToContain('No model class')
            ->assertFailed();
    }

    public function test_rejects_a_model_that_does_not_implement_seoable(): void
    {
        $this->artisan('seo:audit', ['model' => \stdClass::class])->assertFailed();
    }

    public function test_a_batch_with_no_records_still_finishes_cleanly(): void
    {
        $this->artisan('seo:audit', ['model' => Post::class])
            ->expectsOutputToContain('no records found')
            ->assertSuccessful();

        $batch = AuditBatch::query()->first();

        $this->assertSame(0, $batch->total_records);
        $this->assertNull($batch->average_score);
        $this->assertNotNull($batch->finished_at);
    }

    public function test_scores_every_record_and_stores_one_audit_row_each(): void
    {
        $this->makePost(['name' => 'Bài viết mẫu', 'excerpt' => str_repeat('Nội dung chất lượng cao. ', 40)]);
        $this->makePost(['name' => 'Bài viết khác', 'excerpt' => 'Ngắn quá']);

        $this->artisan('seo:audit', ['model' => Post::class, '--content' => 'excerpt'])
            ->assertSuccessful();

        $batch = AuditBatch::query()->first();

        $this->assertSame(2, $batch->total_records);
        $this->assertCount(2, Audit::query()->where('batch_id', $batch->id)->get());
        $this->assertNotNull($batch->average_score);
        $this->assertNotNull($batch->min_score);
        $this->assertNotNull($batch->max_score);
    }

    public function test_each_audit_row_records_its_own_record_and_failed_checks(): void
    {
        $post = $this->makePost(['name' => 'X', 'excerpt' => 'Ngắn']);

        $this->artisan('seo:audit', ['model' => Post::class, '--content' => 'excerpt'])
            ->assertSuccessful();

        $audit = Audit::query()->first();

        $this->assertSame($post->seoType(), $audit->seoable_type);
        $this->assertSame((string) $post->getKey(), $audit->seoable_id);
        $this->assertIsArray($audit->failed_checks);
    }

    public function test_a_missing_content_attribute_is_treated_as_empty_rather_than_erroring(): void
    {
        $this->makePost();

        $this->artisan('seo:audit', ['model' => Post::class, '--content' => 'no_such_column'])
            ->assertSuccessful();

        $this->assertSame(1, AuditBatch::query()->first()->total_records);
    }

    public function test_each_run_creates_a_new_batch_rather_than_reusing_the_last_one(): void
    {
        $this->makePost();

        $this->artisan('seo:audit', ['model' => Post::class])->assertSuccessful();
        $this->artisan('seo:audit', ['model' => Post::class])->assertSuccessful();

        $this->assertSame(2, AuditBatch::query()->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePost(array $attributes = []): Post
    {
        return Post::query()->create($attributes + [
            'name' => 'Bài viết mẫu',
            'slug' => 'bai-viet-'.uniqid(),
        ]);
    }
}
