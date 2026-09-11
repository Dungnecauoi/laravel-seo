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

    public function test_not_found_ingest_token_is_writable_and_marked_secret(): void
    {
        $this->repo()->set('not_found.ingest_token', 'a-secret-token');

        $this->assertSame('a-secret-token', config('seo.not_found.ingest_token'));
        $this->assertTrue($this->repo()->isSecret('not_found.ingest_token'));
    }

    public function test_a_sample_rate_setting_rejects_a_value_outside_zero_to_one(): void
    {
        $this->expectException(InvalidSettingValue::class);

        $this->repo()->set('not_found.sample_rate', 1.5);
    }

    public function test_a_sample_rate_setting_accepts_the_boundary_values(): void
    {
        // A JSON round trip through the settings table does not preserve
        // 0.0 as distinct from the integer 0 — the validator itself
        // accepts either shape, so the assertion compares numerically.
        $this->repo()->set('not_found.sample_rate', 0.0);
        $this->assertEquals(0.0, config('seo.not_found.sample_rate'));

        $this->repo()->set('not_found.sample_rate', 1);
        $this->assertEquals(1.0, config('seo.not_found.sample_rate'));
    }

    public function test_an_integer_setting_rejects_a_non_integer_value(): void
    {
        $this->expectException(InvalidSettingValue::class);

        $this->repo()->set('not_found.max_rows', 'a lot');
    }

    public function test_an_integer_setting_accepts_zero_which_disables_the_row_cap(): void
    {
        // NotFoundLogger's own enforceRowLimit() treats <= 0 as uncapped —
        // the validator must not reject that shape before it gets there.
        $this->repo()->set('not_found.max_rows', 0);

        $this->assertSame(0, config('seo.not_found.max_rows'));
    }

    public function test_a_regex_list_setting_rejects_a_catastrophic_pattern(): void
    {
        $this->expectException(InvalidSettingValue::class);

        $this->repo()->set('not_found.exclude', ['#(a+)+$#']);
    }

    public function test_a_regex_list_setting_rejects_an_alternation_shaped_catastrophic_pattern(): void
    {
        // A different vulnerable shape from the nested-quantifier case
        // above — ambiguous alternation, not a repeated group. Catching
        // this only by running the pattern (not by recognising one
        // specific text shape) is the whole point of the detector.
        $this->expectException(InvalidSettingValue::class);

        $this->repo()->set('not_found.exclude', ['#(a|aa)+$#']);
    }

    public function test_a_regex_list_setting_rejects_an_invalid_pattern(): void
    {
        $this->expectException(InvalidSettingValue::class);

        $this->repo()->set('not_found.exclude', ['#unclosed[']);
    }

    public function test_a_regex_list_setting_accepts_a_valid_list(): void
    {
        $this->repo()->set('not_found.exclude', ['#\.(js|css)$#i']);

        $this->assertSame(['#\.(js|css)$#i'], config('seo.not_found.exclude'));
    }

    public function test_a_host_list_setting_rejects_a_full_url(): void
    {
        $this->expectException(InvalidSettingValue::class);

        $this->repo()->set('redirects.allowed_hosts', ['https://cdn.example.com']);
    }

    public function test_a_host_list_setting_accepts_a_bare_hostname(): void
    {
        $this->repo()->set('redirects.allowed_hosts', ['cdn.example.com']);

        $this->assertSame(['cdn.example.com'], config('seo.redirects.allowed_hosts'));
    }

    public function test_redirects_boolean_settings_round_trip(): void
    {
        $this->repo()->set('redirects.enabled', false);
        $this->repo()->set('redirects.eager', true);
        $this->repo()->set('redirects.keep_query', false);

        $this->assertFalse(config('seo.redirects.enabled'));
        $this->assertTrue(config('seo.redirects.eager'));
        $this->assertFalse(config('seo.redirects.keep_query'));
    }

    private function repo(): SettingsRepository
    {
        return $this->app->make(SettingsRepository::class);
    }
}
