<?php

declare(strict_types=1);

namespace Duxbo\Seo\Enums;

/**
 * How much damage a tool can do, and therefore which Gate ability a caller
 * needs to see it in {@see \Duxbo\Seo\Ai\Tools\AiToolRegistry::manifest()}
 * or invoke it through {@see \Duxbo\Seo\Ai\Tools\AiToolDispatcher}.
 */
enum AiToolRisk: string
{
    /** Never mutates anything. Runs immediately, no proposal step. */
    case Read = 'read';

    /** Mutates state, but the mutation is ordinary and reversible. */
    case Write = 'write';

    /** Deletes data or has an external side effect that cannot be undone. */
    case Destructive = 'destructive';

    /**
     * The Gate ability a caller must hold to see or invoke a tool at this
     * tier — three separate abilities rather than one, so an application
     * can let an AI agent read and propose without also handing it delete.
     */
    public function gateAbility(): string
    {
        return match ($this) {
            self::Read => 'viewSeoPanel',
            self::Write => 'useSeoAiWrites',
            self::Destructive => 'useSeoAiDestructive',
        };
    }
}
