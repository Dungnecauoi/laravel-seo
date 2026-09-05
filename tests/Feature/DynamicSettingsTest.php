<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Exceptions\InvalidSettingValue;
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

    public function test_a_boolean_setting_rejects_a_non_boolean_value(): void
    {
        $this->expectException(InvalidSettingValue::class);

        // json_decode gives back a string here, not the loose PHP truthiness
        // config('seo.*') === true comparisons throughout the package expect.
        $this->repo()->set('robots.block_ai_crawlers', 'true');
    }

    public function test_a_url_setting_rejects_a_non_http_scheme(): void
    {
        $this->expectException(InvalidSettingValue::class);

        $this->repo()->set('schema.organization.logo', 'javascript:alert(1)');
    }

    public function test_a_url_list_setting_rejects_one_bad_entry_in_an_otherwise_valid_list(): void
    {
        $this->expectException(InvalidSettingValue::class);

        $this->repo()->set('schema.organization.sameAs', [
            'https://twitter.com/example',
            'not-a-url',
        ]);
    }

    public function test_a_twitter_card_setting_rejects_a_value_the_enum_does_not_define(): void
    {
        $this->expectException(InvalidSettingValue::class);

        $this->repo()->set('defaults.twitter.card', 'summary_extra_huge');
    }

    public function test_an_indexnow_key_rejects_characters_that_would_corrupt_the_route(): void
    {
        // A "{" registers a *dynamic* route parameter instead of the literal
        // path this key is meant to be — see IndexNowKeyValidator's docblock.
        $this->expectException(InvalidSettingValue::class);

        $this->repo()->set('indexnow.key', '{evil}');
    }

    public function test_a_valid_indexnow_key_is_accepted(): void
    {
        $this->repo()->set('indexnow.key', 'a1b2c3d4e5f6');

        $this->assertSame('a1b2c3d4e5f6', config('seo.indexnow.key'));
    }

    public function test_setting_a_value_to_null_is_still_allowed_for_a_nullable_string_setting(): void
    {
        $this->repo()->set('verification.google', null);

        $this->assertNull(config('seo.verification.google'));
    }

    private function repo(): SettingsRepository
    {
        return $this->app->make(SettingsRepository::class);
    }
}
