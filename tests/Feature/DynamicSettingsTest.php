<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Exceptions\UnknownSetting;
use Duxbo\Seo\Settings\SettingsRepository;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class DynamicSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.settings.enabled', true);
    }

    public function test_disabled_by_default_means_no_overrides_and_no_query(): void
    {
        config(['seo.settings.enabled' => false]);

        $this->assertSame([], $this->repo()->all());
    }

    public function test_set_rejects_a_key_outside_the_allowlist(): void
    {
        $this->expectException(UnknownSetting::class);
        $this->expectExceptionMessage('storage.connection');

        $this->repo()->set('storage.connection', 'mysql');
    }

    public function test_set_persists_and_immediately_applies_to_the_live_config(): void
    {
        $this->repo()->set('verification.google', 'abc123');

        $this->assertSame('abc123', config('seo.verification.google'));
        $this->assertDatabaseHas('seo_settings', ['key' => 'verification.google']);
    }

    public function test_forget_removes_the_stored_row_and_has_reports_false(): void
    {
        $this->repo()->set('verification.google', 'abc123');
        $this->repo()->forget('verification.google');

        $this->assertFalse($this->repo()->has('verification.google'));
        $this->assertDatabaseMissing('seo_settings', ['key' => 'verification.google']);
    }

    public function test_forget_also_rejects_a_key_outside_the_allowlist(): void
    {
        $this->expectException(UnknownSetting::class);

        $this->repo()->forget('storage.connection');
    }

    public function test_get_falls_back_to_the_given_default_when_nothing_is_stored(): void
    {
        $this->assertSame('fallback', $this->repo()->get('verification.google', 'fallback'));
    }

    public function test_an_override_actually_changes_downstream_html_output(): void
    {
        // The point of this feature: nothing in HtmlFormatter changed to
        // support it — set() only ever rewrites what config() returns.
        $this->repo()->set('verification.google', 'live-code-123');

        $post = Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);
        $html = (string) $post->seoTags();

        $this->assertStringContainsString(
            '<meta name="google-site-verification" content="live-code-123">',
            $html,
        );
    }

    public function test_a_boolean_setting_round_trips_correctly_through_json_storage(): void
    {
        $this->repo()->set('robots.block_ai_crawlers', true);

        $this->assertTrue(config('seo.robots.block_ai_crawlers'));
        $this->assertTrue($this->repo()->get('robots.block_ai_crawlers'));
    }

    private function repo(): SettingsRepository
    {
        return $this->app->make(SettingsRepository::class);
    }
}
