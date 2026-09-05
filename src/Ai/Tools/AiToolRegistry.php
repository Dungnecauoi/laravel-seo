<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

/**
 * Every registered {@see AiTool}, discovered from `seo.ai.tools.enabled` the
 * same way `seo.analysis.checks` discovers content-analysis checks — a
 * plain list of class strings, resolved through the container.
 */
final class AiToolRegistry
{
    /** @var array<string, AiTool> */
    private array $tools;

    public function __construct(Application $app, Config $config)
    {
        /** @var list<class-string<AiTool>> $classes */
        $classes = $config->get('seo.ai.tools.enabled', []);

        $tools = [];

        foreach ($classes as $class) {
            /** @var AiTool $tool */
            $tool = $app->make($class);
            $tools[$tool->name()] = $tool;
        }

        $this->tools = $tools;
    }

    /**
     * @return list<AiTool>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    public function find(string $name): ?AiTool
    {
        return $this->tools[$name] ?? null;
    }

    public function authorized(AiTool $tool, AiToolContext $context): bool
    {
        return Gate::forUser($context->user)->allows($tool->riskTier()->gateAbility());
    }

    /**
     * Every tool the caller is actually allowed to invoke, described well
     * enough to serve as an OpenAI/Anthropic tool definition or an MCP
     * `tools/list` entry — a tool this caller cannot use is left off
     * entirely rather than listed and then refused, so a manifest never
     * advertises more than a caller can act on.
     *
     * @return list<array<string, mixed>>
     */
    public function manifest(AiToolContext $context): array
    {
        $described = [];

        foreach ($this->tools as $tool) {
            if (! $this->authorized($tool, $context)) {
                continue;
            }

            $described[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'input_schema' => $tool->inputSchema(),
                'risk_tier' => $tool->riskTier()->value,
            ];
        }

        return $described;
    }
}
