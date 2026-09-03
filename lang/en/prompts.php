<?php

declare(strict_types=1);

/*
| Prompts are translated, not just their output. An English instruction asking
| for a Vietnamese description reliably produces stilted Vietnamese.
*/

return [

    'system' => 'You write search metadata. Answer only with the requested structure. '
        .'Write in the same language as the content you are given. Never invent facts that '
        .'are not in the content.',

    'meta' => <<<'TEXT'
    Write a page title and meta description for the content below.

    Focus keyword: :keyword

    The title should read naturally and include the focus keyword where it fits.
    The description must be at most :max characters, say what the page offers,
    and end with a complete sentence.

    Content:
    :content
    TEXT,

    'keywords' => <<<'TEXT'
    Read the content below and suggest between three and eight search phrases
    someone would actually type to find it. Prefer specific phrases over single
    broad words. Use the language the content is written in.

    Content:
    :content
    TEXT,

];
