<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Console;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Illuminate\Support\Facades\Artisan;

/**
 * Wraps one Artisan command as an AI tool.
 *
 * `Artisan::call()` only ever returns an exit code and whatever it printed —
 * there is no structured data to hand back the way every other tool in this
 * package has, since these commands were written for a terminal, not an
 * API. `execute()` reflects that honestly: `{exitCode, output}`, not a shape
 * pretending to be richer than it is.
 *
 * Runs synchronously, inside whichever request or MCP call invoked it — the
 * same "no cost control" caveat `AnalyzeController`'s own docblock already
 * carries for `POST /analyze`. A command over a very large table can make
 * this take a while; nothing here queues it.
 */
abstract class ConsoleCommandTool implements AiTool
{
    abstract protected function signature(): string;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    abstract protected function arguments(array $input): array;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(array $input, AiToolContext $context): ?array
    {
        $exitCode = Artisan::call($this->signature(), $this->arguments($input));

        return [
            'exitCode' => $exitCode,
            'output' => trim(Artisan::output()),
        ];
    }

    /**
     * Renders `arguments()`'s Laravel-shaped array (`['model' => 'x',
     * '--content' => 'body']`) back into what someone would actually type,
     * for a preview line — `x --content=body`.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function commandLine(array $arguments): string
    {
        $parts = [];

        foreach ($arguments as $key => $value) {
            $parts[] = match (true) {
                $value === true => $key,
                str_starts_with((string) $key, '--') => "{$key}={$value}",
                default => (string) $value,
            };
        }

        return trim($this->signature().' '.implode(' ', $parts));
    }
}
