<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Fixtures;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;

/**
 * A minimal Destructive-tier tool, deliberately without {@see \Duxbo\Seo\Contracts\AiToolPreviewable}
 * — exercises the dispatcher's generic "no dry-run available" preview line.
 */
final class FakeDestructiveTool implements AiTool
{
    public static int $calls = 0;

    public static function reset(): void
    {
        self::$calls = 0;
    }

    public function name(): string
    {
        return 'fake.destructive';
    }

    public function description(): string
    {
        return 'Test double.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Destructive;
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        self::$calls++;

        return null;
    }
}
