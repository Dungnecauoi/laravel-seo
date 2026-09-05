<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Enums\RedirectMatchType;
use Duxbo\Seo\Enums\RedirectType;
use Duxbo\Seo\Exceptions\UnsafeRedirect;
use Duxbo\Seo\Redirects\Redirect;
use Duxbo\Seo\Redirects\RedirectRepository;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

final class RedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.redirects.cache_ttl', 0);
        $app['config']->set('app.url', 'http://localhost');
    }

    protected function defineRoutes($router): void
    {
        $router->get('/ton-tai', static fn (): string => 'ok');
    }

    public function test_an_exact_rule_redirects_a_missing_path(): void
    {
        $this->redirects()->create('/cu', '/ton-tai');

        $this->get('/cu')->assertRedirect('/ton-tai')->assertStatus(301);
    }

    public function test_an_existing_route_is_left_alone(): void
    {
        $this->redirects()->create('/ton-tai', '/somewhere-else');

        // Rules are consulted only once a request has already 404ed, so a live
        // route is never shadowed by a stale rule.
        $this->get('/ton-tai')->assertOk()->assertSee('ok');
    }

    public function test_a_prefix_rule_carries_the_remainder_across(): void
    {
        $this->redirects()->create('/blog', '/tin-tuc', type: RedirectMatchType::Prefix);

        // A moved section keeps its deep links instead of dumping every
        // visitor on one page.
        $this->get('/blog/bai-viet/2026')->assertRedirect('/tin-tuc/bai-viet/2026');
    }

    public function test_the_longest_matching_prefix_wins(): void
    {
        $this->redirects()->create('/blog', '/tin-tuc', type: RedirectMatchType::Prefix);
        $this->redirects()->create('/blog/2024', '/luu-tru', type: RedirectMatchType::Prefix);

        $this->get('/blog/2024/thang-1')->assertRedirect('/luu-tru/thang-1');
    }

    public function test_a_regex_rule_can_use_capture_groups(): void
    {
        $this->redirects()->create(
            '#^/san-pham/(\d+)$#',
            '/products/$1',
            type: RedirectMatchType::Regex,
        );

        $this->get('/san-pham/42')->assertRedirect('/products/42');
    }

    public function test_the_query_string_is_carried_over(): void
    {
        $this->redirects()->create('/cu', '/ton-tai');

        $this->get('/cu?utm_source=facebook')->assertRedirect('/ton-tai?utm_source=facebook');
    }

    public function test_a_gone_rule_ends_the_request_rather_than_redirecting(): void
    {
        $this->redirects()->create('/da-xoa', null, status: RedirectType::Gone);

        $this->get('/da-xoa')->assertStatus(410);
    }

    public function test_a_redirect_to_another_host_is_refused(): void
    {
        // Anyone able to write rules could otherwise turn a trusted URL on this
        // domain into a phishing link.
        $this->expectException(UnsafeRedirect::class);
        $this->expectExceptionMessageMatches('/phishing/');

        $this->redirects()->create('/khuyen-mai', 'https://trang-lua-dao.com');
    }

    public function test_an_allowlisted_host_is_permitted(): void
    {
        config(['seo.redirects.allowed_hosts' => ['shop.example.com']]);

        $redirect = $this->redirects()->create('/shop', 'https://shop.example.com/vn');

        $this->assertSame('https://shop.example.com/vn', $redirect->target);
    }

    public function test_a_protocol_relative_target_is_refused(): void
    {
        // "//evil.com" looks like a path and is not one.
        $this->expectException(UnsafeRedirect::class);

        $this->redirects()->create('/cu', '//evil.com');
    }

    public function test_a_redirect_loop_is_refused_at_write_time(): void
    {
        $this->redirects()->create('/a', '/b');

        $this->expectException(UnsafeRedirect::class);
        $this->expectExceptionMessageMatches('/loop/');

        $this->redirects()->create('/b', '/a');
    }

    public function test_a_catastrophic_regex_is_refused(): void
    {
        // Nested quantifiers backtrack exponentially on a crafted path and hang
        // the request.
        $this->expectException(UnsafeRedirect::class);
        $this->expectExceptionMessageMatches('/quantifier/');

        $this->redirects()->create('^/(a+)+$', '/x', type: RedirectMatchType::Regex);
    }

    public function test_an_invalid_pattern_is_refused(): void
    {
        $this->expectException(UnsafeRedirect::class);

        $this->redirects()->create('#^/[unclosed#', '/x', type: RedirectMatchType::Regex);
    }

    public function test_a_disabled_rule_stops_matching(): void
    {
        $this->redirects()->create('/cu', '/ton-tai');
        $this->redirects()->disable('/cu');

        $this->get('/cu')->assertNotFound();
    }

    public function test_writing_a_row_directly_through_eloquent_still_refuses_an_unsafe_target(): void
    {
        // RedirectRepository::create() is the intended entry point, but
        // nothing stops a seeder or a careless integration from calling
        // Redirect::create() straight — the model's own `saving` guard is
        // what actually closes that gap, not the repository alone.
        $this->expectException(UnsafeRedirect::class);
        $this->expectExceptionMessageMatches('/phishing/');

        Redirect::create([
            'source_path' => '/khuyen-mai-2',
            'source_hash' => md5('/khuyen-mai-2'),
            'source_type' => RedirectMatchType::Exact,
            'target' => 'https://trang-lua-dao.com',
            'status_code' => RedirectType::MovedPermanently,
            'is_active' => true,
        ]);
    }

    public function test_writing_a_row_directly_through_eloquent_still_refuses_a_loop(): void
    {
        $this->redirects()->create('/a', '/b');

        $this->expectException(UnsafeRedirect::class);
        $this->expectExceptionMessageMatches('/loop/');

        Redirect::create([
            'source_path' => '/b',
            'source_hash' => md5('/b'),
            'source_type' => RedirectMatchType::Exact,
            'target' => '/a',
            'status_code' => RedirectType::MovedPermanently,
            'is_active' => true,
        ]);
    }

    public function test_re_enabling_a_disabled_rule_refuses_a_loop_formed_while_it_was_off(): void
    {
        $rule = $this->redirects()->create('/x', '/y');
        $this->redirects()->disable('/x');

        // With /x safely inactive, /y -> /x no longer loops back to anything
        // live, so this second rule is allowed to point at it.
        $this->redirects()->create('/y', '/x');

        // Re-enabling /x now closes /x -> /y -> /x -> ... and must be refused,
        // not silently restored the way a bulk `update()` used to allow.
        $this->expectException(UnsafeRedirect::class);
        $this->expectExceptionMessageMatches('/loop/');

        try {
            $this->redirects()->setActive($rule->id, true);
        } finally {
            $this->assertFalse($rule->fresh()->is_active);
        }
    }

    public function test_hits_are_counted(): void
    {
        $redirect = $this->redirects()->create('/cu', '/ton-tai');

        $this->get('/cu');
        $this->get('/cu');

        $this->assertSame(2, $redirect->fresh()->hits);
    }

    private function redirects(): RedirectRepository
    {
        return app(RedirectRepository::class);
    }
}
