<?php

declare(strict_types=1);

return [
    'errored' => 'This check could not run.',
    'skipped' => 'Not applicable to this page.',
    'keyword_in_title' => [
        'pass' => 'The focus keyword is in the title.',
        'fail' => 'The focus keyword is missing from the title.',
        'hint' => 'Put the keyword in the title, as near the start as reads naturally.',
    ],
    'keyword_in_description' => [
        'pass' => 'The focus keyword is in the meta description.',
        'fail' => 'The focus keyword is missing from the meta description.',
        'hint' => 'Work the keyword into the description; it is bolded in search results.',
    ],
    'keyword_in_url' => [
        'pass' => 'The focus keyword is in the URL.',
        'fail' => 'The focus keyword is missing from the URL.',
        'hint' => 'Include the keyword in the slug. Changing a published URL needs a redirect.',
    ],
    'keyword_in_opening' => [
        'pass' => 'The focus keyword appears early in the content.',
        'fail' => 'The focus keyword does not appear in the opening.',
        'hint' => 'Mention the keyword in the first paragraph.',
    ],
    'keyword_in_headings' => [
        'pass' => 'The focus keyword appears in a subheading.',
        'fail' => 'None of the :headings subheadings contain the focus keyword.',
        'none' => 'The content has no H2 or H3 subheadings.',
        'hint' => 'Add a subheading that uses the keyword, so the structure states the topic.',
    ],
    'keyword_in_image_alt' => [
        'pass' => 'An image alt text contains the focus keyword.',
        'fail' => 'No image alt text contains the focus keyword.',
        'hint' => 'Describe one image using the keyword, where it genuinely fits.',
    ],
    'keyword_density' => [
        'pass' => 'Keyword density is :density% across :occurrences occurrence(s).',
        'low' => 'Keyword density is only :density%.',
        'high' => 'Keyword density is :density%, which reads as stuffing.',
        'hint_low' => 'Mention the keyword a little more often, where it fits.',
        'hint_high' => 'Cut some occurrences and use natural variations instead.',
    ],
    'title_length' => [
        'pass' => 'Title width is :pixels px.',
        'missing' => 'The page has no title.',
        'long' => 'Title is :pixels px and will be cut off after about :max px.',
        'short' => 'Title is only :pixels px, leaving space unused.',
        'hint_missing' => 'Give the page a title.',
        'hint_long' => 'Shorten the title so it fits the search result.',
        'hint_short' => 'Lengthen the title to use the available width.',
    ],
    'description_length' => [
        'pass' => 'Meta description is :length characters.',
        'missing' => 'The page has no meta description.',
        'short' => 'Meta description is :length characters, under the :min recommended.',
        'long' => 'Meta description is :length characters, over the :max recommended.',
        'hint_missing' => 'Write a description; otherwise the search engine invents one.',
        'hint_short' => 'Expand the description to use the space.',
        'hint_long' => 'Trim the description so it is not cut off.',
    ],
    'content_length' => [
        'pass' => 'Content is :count syllables.',
        'short' => 'Content is :count syllables, under the :minimum recommended.',
        'hint' => 'Cover the topic more fully, or accept that this is a short page.',
    ],
    'internal_links' => [
        'pass' => ':count internal link(s).',
        'none' => 'The content has no internal links.',
        'hint' => 'Link to related pages so visitors and crawlers can move on.',
    ],
    'external_links' => [
        'pass' => ':count external link(s).',
        'none' => 'The content has no external links.',
        'hint' => 'Cite a source where it helps the reader.',
    ],
    'images_have_alt' => [
        'pass' => 'All :total images have alt text.',
        'fail' => ':missing of :total images have no alt text.',
        'hint' => 'Describe every image. Screen readers need it and image search uses it.',
    ],
    'single_h1' => [
        'pass' => 'The page has exactly one H1.',
        'none' => 'The page has no H1.',
        'many' => 'The page has :count H1 headings.',
        'hint' => 'Use exactly one H1 to state what the page is about.',
    ],
    'vi_readability' => [
        'easy' => 'Sentences average :average syllables — comfortable.',
        'medium' => 'Sentences average :average syllables — moderately demanding.',
        'hard' => 'Sentences average :average syllables — hard going, with :long_sentences long sentence(s).',
        'hint' => 'Split the longest sentences. This is a working heuristic, not a validated readability score.',
    ],
    'vi_passive' => [
        'pass' => ':ratio% of sentences use a passive marker.',
        'high' => ':ratio% of sentences use a passive marker (:passive of :total).',
        'hint' => 'Consider rewriting some in the active voice. "được" and "bị" also occur in active sentences, so treat this as a hint.',
    ],
    'en_flesch' => [
        'pass' => 'Flesch Reading Ease is :score.',
        'hard' => 'Flesch Reading Ease is :score, which is demanding.',
        'hint' => 'Use shorter sentences and simpler words.',
    ],
];
