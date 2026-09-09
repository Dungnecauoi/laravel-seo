<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Audit\Audit;
use Duxbo\Seo\Audit\AuditBatch;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

    public function test_external_signals_are_null_when_nothing_has_ever_checked_this_url(): void
    {
        $this->makePost();

        $this->artisan('seo:audit', ['model' => Post::class])->assertSuccessful();

        $audit = Audit::query()->first();
        $batch = AuditBatch::query()->first();

        $this->assertNull($audit->pagespeed_score);
        $this->assertNull($audit->gsc_verdict);
        $this->assertNull($audit->broken_links_count);
        $this->assertNull($batch->average_pagespeed_score);
        $this->assertNull($batch->records_not_indexed);
        $this->assertNull($batch->records_with_broken_links);
    }

    public function test_joins_in_the_latest_stored_pagespeed_score_for_the_records_url(): void
    {
        $post = $this->makePost(['slug' => 'toc-do']);

        DB::table('seo_pagespeed_stats')->insert([
            ['url' => $post->seoUrl(), 'url_hash' => md5($post->seoUrl()), 'strategy' => 'mobile', 'date' => now()->subDay()->toDateString(), 'performance_score' => 40, 'created_at' => now(), 'updated_at' => now()],
            ['url' => $post->seoUrl(), 'url_hash' => md5($post->seoUrl()), 'strategy' => 'mobile', 'date' => now()->toDateString(), 'performance_score' => 96, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('seo:audit', ['model' => Post::class])
            ->expectsOutputToContain('Average PageSpeed: 96')
            ->assertSuccessful();

        $this->assertSame(96, Audit::query()->first()->pagespeed_score);
        $this->assertEquals(96.0, AuditBatch::query()->first()->average_pagespeed_score);
    }

    public function test_joins_in_the_latest_gsc_verdict_and_counts_it_as_not_indexed(): void
    {
        $post = $this->makePost(['slug' => 'chua-index']);

        DB::table('seo_url_inspections')->insert([
            'url' => $post->seoUrl(), 'url_hash' => md5($post->seoUrl()), 'date' => now()->toDateString(),
            'verdict' => 'FAIL', 'coverage_state' => 'Crawled - currently not indexed',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('seo:audit', ['model' => Post::class])
            ->expectsOutputToContain("1 record(s) not passing Search Console's own index check")
            ->assertSuccessful();

        $this->assertSame('FAIL', Audit::query()->first()->gsc_verdict);
        $this->assertSame(1, AuditBatch::query()->first()->records_not_indexed);
    }

    public function test_a_passing_gsc_verdict_is_not_counted_as_not_indexed(): void
    {
        $post = $this->makePost(['slug' => 'da-index']);

        DB::table('seo_url_inspections')->insert([
            'url' => $post->seoUrl(), 'url_hash' => md5($post->seoUrl()), 'date' => now()->toDateString(),
            'verdict' => 'PASS', 'coverage_state' => 'Submitted and indexed',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('seo:audit', ['model' => Post::class])->assertSuccessful();

        $this->assertSame(0, AuditBatch::query()->first()->records_not_indexed);
    }

    public function test_counts_a_records_currently_broken_external_links(): void
    {
        $post = $this->makePost(['slug' => 'link-hong']);

        DB::table('seo_external_links')->insert([
            'source_type' => $post->seoType(), 'source_id' => (string) $post->getKey(),
            'target_url' => 'https://mot-trang-da-mat.com/x', 'target_hash' => md5('https://mot-trang-da-mat.com/x'),
            'created_at' => now(),
        ]);
        DB::table('seo_link_checks')->insert([
            'url' => 'https://mot-trang-da-mat.com/x', 'url_hash' => md5('https://mot-trang-da-mat.com/x'),
            'successful' => false, 'status_code' => 404, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('seo:audit', ['model' => Post::class])
            ->expectsOutputToContain('1 record(s) cite at least one currently-broken external link')
            ->assertSuccessful();

        $this->assertSame(1, Audit::query()->first()->broken_links_count);
        $this->assertSame(1, AuditBatch::query()->first()->records_with_broken_links);
    }

    public function test_a_crawled_record_with_no_broken_links_reports_zero_not_null(): void
    {
        $post = $this->makePost(['slug' => 'link-song']);

        DB::table('seo_external_links')->insert([
            'source_type' => $post->seoType(), 'source_id' => (string) $post->getKey(),
            'target_url' => 'https://van-con-song.com/x', 'target_hash' => md5('https://van-con-song.com/x'),
            'created_at' => now(),
        ]);
        DB::table('seo_link_checks')->insert([
            'url' => 'https://van-con-song.com/x', 'url_hash' => md5('https://van-con-song.com/x'),
            'successful' => true, 'status_code' => 200, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('seo:audit', ['model' => Post::class])->assertSuccessful();

        $this->assertSame(0, Audit::query()->first()->broken_links_count);
        $this->assertSame(0, AuditBatch::query()->first()->records_with_broken_links);
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
