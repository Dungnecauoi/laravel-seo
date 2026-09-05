<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use RuntimeException;

final class AiToolProposalExpired extends RuntimeException implements SeoException
{
    public static function forId(string $proposalId): self
    {
        return new self(sprintf(
            'Proposal [%s] is unknown or has expired. Call the tool again without '
            .'"confirm" to get a fresh proposal id before confirming.',
            $proposalId,
        ));
    }
}
