<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\TestCase;

final class RobotsTxtTest extends TestCase
{
    public function test_ai_crawlers_are_not_blocked_by_default(): void
    {
        $body = (string) $this->get('/robots.txt')->getContent();

        $this->assertStringNotContainsString('GPTBot', $body);
    }

    public function test_blocking_ai_crawlers_disallows_each_one_without_touching_the_default_group(): void
    {
        config([
            'seo.robots.block_ai_crawlers' => true,
            'seo.robots.ai_crawlers' => ['GPTBot', 'ClaudeBot'],
        ]);

        $body = (string) $this->get('/robots.txt')->getContent();

        $this->assertStringContainsString("User-agent: GPTBot\nDisallow: /", $body);
        $this->assertStringContainsString("User-agent: ClaudeBot\nDisallow: /", $body);

        // The default group is a separate decision — search engines must
        // still be allowed to crawl even though AI training bots are not.
        $this->assertStringContainsString("User-agent: *", $body);
        $this->assertStringNotContainsString("User-agent: *\nDisallow: /", $body);
    }

    public function test_an_unindexable_site_still_disallows_everything_regardless_of_the_ai_crawler_setting(): void
    {
        config([
            'seo.enabled' => false,
            'seo.robots.block_ai_crawlers' => true,
        ]);

        $body = (string) $this->get('/robots.txt')->getContent();

        $this->assertStringContainsString("User-agent: *\nDisallow: /", $body);
        $this->assertStringNotContainsString('GPTBot', $body);
    }
}
