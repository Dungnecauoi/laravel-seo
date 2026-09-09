<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Facades\Seo;
use Duxbo\Seo\Tests\TestCase;

/**
 * Tracking scripts are site-wide and unrelated to any specific record —
 * unlike everything else `Seo` renders, so these tests only check that a
 * configured snippet comes back exactly as given, not that it varies with a
 * page. Mirrors {@see SiteVerificationTest}'s scope for the same reason.
 */
final class TrackingScriptsTest extends TestCase
{
    public function test_nothing_is_emitted_when_neither_is_configured(): void
    {
        $this->assertSame('', (string) Seo::trackingHead());
        $this->assertSame('', (string) Seo::trackingBodyOpen());
    }

    public function test_the_head_snippet_is_echoed_back_exactly_as_configured(): void
    {
        $snippet = "<script>gtag('config', 'G-ABC123');</script>";
        config(['seo.tracking.head' => $snippet]);

        $this->assertSame($snippet, (string) Seo::trackingHead());
    }

    public function test_the_body_open_snippet_is_echoed_back_exactly_as_configured(): void
    {
        $snippet = '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXX"></iframe></noscript>';
        config(['seo.tracking.body_open' => $snippet]);

        $this->assertSame($snippet, (string) Seo::trackingBodyOpen());
    }

    public function test_the_snippet_is_never_escaped(): void
    {
        // The whole point is real markup — HtmlString, not a plain string a
        // Blade {{ }} would later escape, is what proves that.
        config(['seo.tracking.head' => '<script>window.x = 1 < 2;</script>']);

        $html = Seo::trackingHead();

        $this->assertInstanceOf(\Illuminate\Support\HtmlString::class, $html);
        $this->assertStringContainsString('1 < 2', (string) $html);
    }

    public function test_head_and_body_open_are_independent(): void
    {
        config(['seo.tracking.head' => '<script>head</script>']);

        $this->assertSame('', (string) Seo::trackingBodyOpen());
    }
}
