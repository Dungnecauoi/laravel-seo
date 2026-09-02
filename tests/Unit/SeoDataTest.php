<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Unit;

use Duxbo\Seo\Data\RobotsRule;
use Duxbo\Seo\Data\SeoData;
use Duxbo\Seo\Enums\RobotsDirective;
use Duxbo\Seo\Exceptions\InvalidSeoData;
use PHPUnit\Framework\TestCase;

final class SeoDataTest extends TestCase
{
    public function test_with_returns_a_copy_and_leaves_the_original_alone(): void
    {
        $original = new SeoData(title: 'First');
        $copy = $original->with(title: 'Second');

        $this->assertSame('First', $original->title);
        $this->assertSame('Second', $copy->title);
    }

    public function test_with_can_set_a_value_back_to_null(): void
    {
        $data = (new SeoData(title: 'Something'))->with(title: null);

        $this->assertNull($data->title);
    }

    public function test_with_rejects_an_unknown_attribute(): void
    {
        $this->expectException(InvalidSeoData::class);

        (new SeoData())->with(titel: 'typo');
    }

    public function test_fill_missing_keeps_decided_values_and_adopts_the_rest(): void
    {
        $decided = new SeoData(title: 'Kept');
        $fallback = new SeoData(title: 'Ignored', description: 'Adopted');

        $result = $decided->fillMissingFrom($fallback);

        $this->assertSame('Kept', $result->title);
        $this->assertSame('Adopted', $result->description);
    }

    public function test_fill_missing_treats_an_empty_robots_list_as_undecided(): void
    {
        $result = (new SeoData())->fillMissingFrom(
            new SeoData(robots: [RobotsRule::noIndex()]),
        );

        $this->assertCount(1, $result->robots);
    }

    public function test_robots_line_drops_a_contradicted_directive(): void
    {
        $data = new SeoData(robots: [
            RobotsRule::make(RobotsDirective::Index),
            RobotsRule::noIndex(),
        ]);

        $this->assertSame('noindex', $data->robotsLine());
    }

    public function test_robots_line_renders_directives_that_carry_a_value(): void
    {
        $data = new SeoData(robots: [
            RobotsRule::noFollow(),
            RobotsRule::maxSnippet(50),
            RobotsRule::maxImagePreview('large'),
        ]);

        $this->assertSame('nofollow, max-snippet:50, max-image-preview:large', $data->robotsLine());
    }

    public function test_robots_line_is_null_when_nothing_was_set(): void
    {
        $this->assertNull((new SeoData())->robotsLine());
    }

    public function test_a_directive_that_needs_a_value_cannot_be_built_without_one(): void
    {
        $this->expectException(InvalidSeoData::class);

        RobotsRule::make(RobotsDirective::MaxSnippet);
    }

    public function test_a_directive_that_takes_no_value_rejects_one(): void
    {
        $this->expectException(InvalidSeoData::class);

        RobotsRule::make(RobotsDirective::NoIndex, 50);
    }
}
