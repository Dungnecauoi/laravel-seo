<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use Duxbo\Seo\Enums\AiToolResultStatus;

/**
 * What {@see \Duxbo\Seo\Ai\Tools\AiToolDispatcher} hands back for one call —
 * an answer for a Read tool, or a proposal to confirm/an applied result for
 * a Write or Destructive one. A tool implementation never builds one of
 * these itself; it returns plain data from `execute()` and the dispatcher
 * decides which of the three this becomes.
 */
final class AiToolResult
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    private function __construct(
        public readonly AiToolResultStatus $status,
        public readonly ?array $data = null,
        public readonly ?string $proposalId = null,
        public readonly ?string $preview = null,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function ok(?array $data): self
    {
        return new self(AiToolResultStatus::Ok, $data);
    }

    public static function proposed(string $proposalId, string $preview): self
    {
        return new self(AiToolResultStatus::Proposed, null, $proposalId, $preview);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function applied(?array $data): self
    {
        return new self(AiToolResultStatus::Applied, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status->value,
            'data' => $this->data,
            'proposal_id' => $this->proposalId,
            'preview' => $this->preview,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
