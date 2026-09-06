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

    'content_fix' => <<<'TEXT'
    Write a page title and meta description for the content below, fixing the
    specific problems listed under "Additional context" — do not just write
    generic meta from scratch, address what is actually flagged.

    Focus keyword: :keyword

    The title should read naturally and include the focus keyword where it fits.
    The description must be at most :max characters, say what the page offers,
    and end with a complete sentence.

    Content:
    :content
    TEXT,

    'redirect_target' => <<<'TEXT'
    A visitor requested this path, which no longer exists:
    :path

    Pick whichever of the following real pages on this site is the closest
    replacement. If none is a good match, pick the least bad one and say so
    plainly in your reasoning — do not invent a URL that is not in this list.

    Candidates:
    :candidates
    TEXT,

    'internal_link' => <<<'TEXT'
    This page has no other page on the site linking to it:
    :orphan_url (":orphan_title")

    From the candidate pages below, pick one to three that are topically
    related enough that a genuine, natural link to the orphan page would fit
    into them, with a short, specific anchor text for each — never a URL that
    is not in this list, and never generic anchor text like "click here".

    Candidates:
    :candidates
    TEXT,

    'context_heading' => 'Additional context:',

    'context_site' => 'This site is called ":brand".',

    'context_current' => 'Currently stored — title: ":title", description: ":description"',

];
