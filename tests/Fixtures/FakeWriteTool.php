<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Fixtures;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;

/**
 * A minimal Write-tier tool for exercising {@see \Duxbo\Seo\Ai\Tools\AiToolDispatcher}'s
 * propose/confirm cycle without needing a real mutating tool to exist yet.
 */
final class FakeWriteTool implements AiTool, AiToolPreviewable
{
    /** @var list<array<string, mixed>> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public function name(): string
    {
        return 'fake.write';
    }

    public function description(): string
    {
        return 'Test double.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['value' => ['type' => 'string']]];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Write;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        return 'Would set value to '.($input['value'] ?? '(none)');
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        self::$calls[] = $input;

        return ['received' => $input];
    }
}
