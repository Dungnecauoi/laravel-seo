<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Data\SitemapVideo;

/**
 * A model whose page carries one or more videos worth declaring in the
 * sitemap's `<video:video>` extension — a page review embedding a product
 * demo, an article with an explainer video.
 *
 * Optional. Attaches to whatever `ModelSource` already yields for this
 * record rather than needing a separate sitemap source: a video sitemap
 * entry belongs on the page that hosts the video, not in a feed of its own.
 */
interface HasSitemapVideo
{
    /**
     * @return list<SitemapVideo>
     */
    public function seoSitemapVideos(): array;
}
