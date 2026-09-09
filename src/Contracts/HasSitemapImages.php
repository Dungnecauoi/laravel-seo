<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

/**
 * A model whose page carries one or more images worth declaring in the
 * sitemap's `<image:image>` extension — a product page's gallery, an
 * article's inline photos — so Google Images can discover them without
 * crawling the page's own HTML first.
 *
 * Optional. Attaches to whatever `ModelSource` already yields for this
 * record rather than needing a separate sitemap source, the same reasoning
 * behind {@see HasSitemapVideo}: an image sitemap entry belongs on the page
 * that hosts the image, not in a feed of its own.
 */
interface HasSitemapImages
{
    /**
     * @return list<string> Absolute image URLs.
     */
    public function seoSitemapImages(): array;
}
