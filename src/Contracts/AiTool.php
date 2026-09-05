<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;

/**
 * One discrete capability of this package, described well enough for an AI
 * agent to discover and call it without hand-written glue: a name, a plain
 * description, a JSON Schema for its input, and a risk tier that decides
 * whether {@see \Duxbo\Seo\Ai\Tools\AiToolDispatcher} runs it immediately or
 * requires a propose/confirm round trip first.
 *
 * `execute()` is where every implementation must resist the temptation to
 * write new logic: it should do nothing but call into an existing service
 * or repository (`Seo`, `RedirectRepository`, `SettingsRepository`, ...) so
 * a tool can never grant an AI caller a capability a human caller does not
 * already have through the API or the panel.
 */
interface AiTool
{
    /**
     * Unique, stable across releases — an AI agent (or a host application)
     * may persist this name to remember what it called.
     */
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema for the input `execute()` accepts — the "properties" and
     * "required" shape, reusable as-is for an OpenAI or Anthropic tool
     * definition or an MCP `inputSchema`.
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    public function riskTier(): AiToolRisk;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    public function execute(array $input, AiToolContext $context): ?array;
}
