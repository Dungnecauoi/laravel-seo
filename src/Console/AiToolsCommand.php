<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\Ai\Tools\AiToolRegistry;
use Duxbo\Seo\Contracts\AiTool;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * A debugging aid: what {@see AiToolRegistry} actually holds, without
 * spinning up an HTTP request or an MCP client to find out.
 */
final class AiToolsCommand extends Command
{
    protected $signature = 'seo:ai:tools';

    protected $description = 'List every AI tool registered in seo.ai.tools.enabled';

    public function handle(AiToolRegistry $registry): int
    {
        $tools = $registry->all();

        if ($tools === []) {
            $this->info('No AI tools registered — check seo.ai.tools.enabled.');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Risk', 'Description'],
            array_map(static fn (AiTool $tool): array => [
                $tool->name(),
                $tool->riskTier()->value,
                Str::limit($tool->description(), 90),
            ], $tools),
        );

        $this->line(sprintf(
            '%d tool(s). REST manifest: GET %s/ai/tools. MCP: POST %s/mcp.',
            count($tools),
            config('seo.api.prefix', 'api/seo/v1'),
            config('seo.api.prefix', 'api/seo/v1'),
        ));

        return self::SUCCESS;
    }
}
