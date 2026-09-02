<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use Duxbo\Seo\Enums\CheckStatus;

/**
 * What one analysis check concluded.
 *
 * `message` states the finding, `hint` says what to do about it. Both are
 * translation keys rather than sentences, so a panel can render either language
 * without the check knowing which.
 */
final class CheckResult
{
    /**
     * @param  array<string, mixed>  $context  Values for the translated message, e.g. counts.
     */
    public function __construct(
        public readonly string $id,
        public readonly CheckStatus $status,
        public readonly string $message,
        public readonly ?string $hint = null,
        public readonly array $context = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function pass(string $id, string $message, array $context = []): self
    {
        return new self($id, CheckStatus::Pass, $message, null, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $id, string $message, ?string $hint = null, array $context = []): self
    {
        return new self($id, CheckStatus::Warning, $message, $hint, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function fail(string $id, string $message, ?string $hint = null, array $context = []): self
    {
        return new self($id, CheckStatus::Fail, $message, $hint, $context);
    }

    public static function skipped(string $id, string $message = 'seo::analysis.skipped'): self
    {
        return new self($id, CheckStatus::Skipped, $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'message' => $this->message,
            'hint' => $this->hint,
            'context' => $this->context,
        ];
    }
}
