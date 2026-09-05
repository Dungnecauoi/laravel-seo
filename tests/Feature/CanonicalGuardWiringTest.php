<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Contracts\CanonicalResolver;
use Duxbo\Seo\Exceptions\UnsafeCanonical;
use Duxbo\Seo\Facades\Seo;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * {@see \Duxbo\Seo\Canonical\CanonicalGuard} is wired into `Seo::save()`, but
 * stays inert with the default {@see \Duxbo\Seo\Canonical\NullCanonicalResolver}
 * binding — these tests exercise both that default no-op and what an
 * application actually unlocks by binding a real resolver.
 */
final class CanonicalGuardWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_arbitrary_canonical_is_never_blocked_by_default(): void
    {
        $post = $this->makePost('bai-1');

        // No CanonicalResolver bound: the guard cannot know this points
        // anywhere real, so it must not invent a problem.
        Seo::save($post, ['canonical' => 'https://khac.example.com/mot-trang-nao-do']);

        $this->assertSame(
            'https://khac.example.com/mot-trang-nao-do',
            Seo::for($post)->canonical,
        );
    }

    public function test_a_page_canonicalizing_to_itself_is_always_allowed(): void
    {
        $this->bindResolver([]);

        $post = $this->makePost('bai-2');

        // The overwhelmingly common, correct case — must never be mistaken
        // for a cycle just because it "points back to" the page it is on.
        Seo::save($post, ['canonical' => $post->seoUrl()]);

        $this->assertSame($post->seoUrl(), Seo::for($post)->canonical);
    }

    public function test_a_cycle_across_two_records_is_refused_once_a_resolver_is_bound(): void
    {
        $a = $this->makePost('bai-a');
        $b = $this->makePost('bai-b');

        // Seo::save() resolves Seo/CanonicalGuard as singletons on first use,
        // so the same $resolver instance backs every save below — its map is
        // mutated in place rather than rebinding the container mid-test,
        // which a already-resolved singleton would simply not see.
        $resolver = $this->bindResolver([$a->seoUrl() => $b->seoUrl()]);

        // A already (validly) canonicalizes to B.
        Seo::save($a, ['canonical' => $b->seoUrl()]);

        // Now B tries to canonicalize back to A — the resolver reports A's
        // own stored canonical (B), so the chain B -> A -> B is a real cycle.
        $resolver->map[$b->seoUrl()] = $a->seoUrl();

        $this->expectException(UnsafeCanonical::class);
        $this->expectExceptionMessageMatches('/cycle/');

        Seo::save($b, ['canonical' => $a->seoUrl()]);
    }

    public function test_a_chain_that_never_loops_is_left_alone(): void
    {
        $a = $this->makePost('chuoi-a');
        $b = $this->makePost('chuoi-b');
        $c = $this->makePost('chuoi-c');

        // A -> B -> C -> (itself), a plain consolidation chain with no cycle.
        $this->bindResolver([
            $b->seoUrl() => $c->seoUrl(),
            $c->seoUrl() => $c->seoUrl(),
        ]);

        Seo::save($a, ['canonical' => $b->seoUrl()]);

        $this->assertSame($b->seoUrl(), Seo::for($a)->canonical);
    }

    /**
     * @param  array<string, string>  $map  Canonical target URL => what that
     *                                      target's own stored canonical is.
     */
    private function bindResolver(array $map): object
    {
        $resolver = new class($map) implements CanonicalResolver {
            /** @param array<string, string> $map */
            public function __construct(public array $map)
            {
            }

            public function resolve(string $url): ?string
            {
                return $this->map[$url] ?? null;
            }
        };

        $this->app->instance(CanonicalResolver::class, $resolver);

        return $resolver;
    }

    private function makePost(string $slug): Post
    {
        return Post::query()->create(['name' => 'Bài viết '.$slug, 'slug' => $slug]);
    }
}
