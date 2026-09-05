<?php

declare(strict_types=1);

namespace Duxbo\Seo\Enums;

enum AiToolResultStatus: string
{
    /** A Read tool's answer, or the immediate result of a tool with nothing to propose. */
    case Ok = 'ok';

    /** A Write/Destructive tool's dry run — nothing was mutated yet. */
    case Proposed = 'proposed';

    /** A previously proposed call was confirmed and actually executed. */
    case Applied = 'applied';
}
