<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Data\AiToolResult;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Exceptions\AiToolNotFound;
use Duxbo\Seo\Exceptions\AiToolProposalExpired;
use Duxbo\Seo\Exceptions\AiToolUnauthorized;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single door every tool call goes through, read or write alike.
 *
 * A `Read` tool runs immediately — nothing to propose. A `Write` or
 * `Destructive` tool runs in two steps: a bare call returns a proposal
 * (nothing mutated yet, just a preview of what would happen), and a second
 * call naming that proposal's id actually executes it, replaying the input
 * that was captured at propose time rather than trusting whatever the
 * confirming call sends — an AI agent (or anything else) cannot widen what
 * it already proposed by changing the input on the confirm step.
 */
final class AiToolDispatcher
{
    public function __construct(
        private readonly AiToolRegistry $registry,
        private readonly Cache $cache,
        private readonly Config $config,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AiToolNotFound
     * @throws AiToolUnauthorized
     * @throws AiToolProposalExpired
     */
    public function call(string $name, array $input, AiToolContext $context, ?string $confirm = null): AiToolResult
    {
        $tool = $this->registry->find($name);

        if ($tool === null) {
            throw AiToolNotFound::named($name, $this->registry->names());
        }

        if (! $this->registry->authorized($tool, $context)) {
            throw AiToolUnauthorized::forTool($name, $tool->riskTier()->gateAbility());
        }

        if ($tool->riskTier() === AiToolRisk::Read) {
            return AiToolResult::ok($tool->execute($input, $context));
        }

        if ($confirm !== null) {
            return $this->confirm($tool, $confirm, $context);
        }

        return $this->propose($tool, $input, $context);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function propose(AiTool $tool, array $input, AiToolContext $context): AiToolResult
    {
        $proposalId = (string) Str::uuid();

        $preview = $tool instanceof AiToolPreviewable
            ? $tool->preview($input, $context)
            : 'No dry-run preview is available for this action.';

        $this->cache->put(
            $this->cacheKey($proposalId),
            ['tool' => $tool->name(), 'input' => $input],
            (int) $this->config->get('seo.ai.tools.proposal_ttl', 900),
        );

        $this->record($tool, $input, $context, 'proposed', $proposalId);

        return AiToolResult::proposed($proposalId, $preview);
    }

    /**
     * @throws AiToolProposalExpired
     */
    private function confirm(AiTool $tool, string $proposalId, AiToolContext $context): AiToolResult
    {
        $stored = $this->cache->get($this->cacheKey($proposalId));

        if (! is_array($stored) || ($stored['tool'] ?? null) !== $tool->name() || ! is_array($stored['input'] ?? null)) {
            throw AiToolProposalExpired::forId($proposalId);
        }

        /** @var array<string, mixed> $input */
        $input = $stored['input'];

        $this->cache->forget($this->cacheKey($proposalId));

        $output = $tool->execute($input, $context);

        $this->record($tool, $input, $context, 'applied', $proposalId, $output);

        return AiToolResult::applied($output);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $output
     */
    private function record(
        AiTool $tool,
        array $input,
        AiToolContext $context,
        string $status,
        string $proposalId,
        ?array $output = null,
    ): void {
        if ($this->config->get('seo.ai.tools.log', true) !== true) {
            return;
        }

        DB::table($this->table())->insert([
            'tool' => $tool->name(),
            'risk_tier' => $tool->riskTier()->value,
            'status' => $status,
            'proposal_id' => $proposalId,
            'input' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'output' => $output !== null ? json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'scope' => $context->scope,
            'actor' => json_encode(['transport' => $context->transport], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'applied_at' => $status === 'applied' ? now() : null,
        ]);
    }

    private function cacheKey(string $proposalId): string
    {
        return 'duxbo.seo.ai_tool_proposal.'.$proposalId;
    }

    private function table(): string
    {
        return (string) $this->config->get('seo.ai.tools.table', 'seo_ai_tool_calls');
    }
}
