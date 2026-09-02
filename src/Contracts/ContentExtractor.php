<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Data\ExtractedContent;

/**
 * Turns stored content into the structures the analyser measures.
 *
 * The default implementation reads HTML with DOMDocument. Swap the binding when
 * content is Markdown, or a block editor's JSON, rather than pre-rendering it
 * to HTML just to satisfy the analyser.
 */
interface ContentExtractor
{
    /**
     * @param  string  $content  Raw stored content, in whatever format this extractor handles.
     */
    public function extract(string $content): ExtractedContent;
}
