<?php

declare(strict_types=1);

namespace Duxbo\Seo\Sitemap;

use DateTimeInterface;
use Duxbo\Seo\Data\SitemapNews;
use Duxbo\Seo\Data\SitemapUrl;
use Duxbo\Seo\Data\SitemapVideo;
use XMLWriter;

/**
 * Writes sitemap XML one element at a time.
 *
 * XMLWriter rather than building a DOM or concatenating strings: a source over
 * a million-row table streams through here in constant memory, and every value
 * is escaped by the writer instead of by hand. Unescaped `&` in a query string
 * is the single most common way a sitemap ends up invalid.
 */
final class SitemapWriter
{
    private XMLWriter $writer;

    private int $count = 0;

    private bool $usesAlternates = false;

    private bool $usesImages = false;

    private bool $usesVideos = false;

    private bool $usesNews = false;

    private function __construct(XMLWriter $writer)
    {
        $this->writer = $writer;
    }

    /**
     * Write to a file on disk.
     */
    public static function toFile(string $path): self
    {
        $writer = new XMLWriter();
        $writer->openUri($path);

        return new self($writer);
    }

    /**
     * Write straight to the response body, so nothing is held in memory.
     */
    public static function toOutput(): self
    {
        $writer = new XMLWriter();
        $writer->openUri('php://output');

        return new self($writer);
    }

    public static function toMemory(): self
    {
        $writer = new XMLWriter();
        $writer->openMemory();

        return new self($writer);
    }

    /**
     * @param  bool  $alternates  Declare the xhtml namespace for hreflang links.
     * @param  bool  $images  Declare the image namespace.
     * @param  bool  $videos  Declare the video namespace.
     * @param  bool  $news  Declare the news namespace.
     */
    public function startUrlSet(
        bool $alternates = false,
        bool $images = false,
        bool $videos = false,
        bool $news = false,
    ): self {
        $this->usesAlternates = $alternates;
        $this->usesImages = $images;
        $this->usesVideos = $videos;
        $this->usesNews = $news;

        $this->writer->startDocument('1.0', 'UTF-8');
        $this->writer->setIndent(true);
        $this->writer->startElement('urlset');
        $this->writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        // Namespaces are declared up front because they must sit on the root
        // element; a source cannot add one once URLs are streaming.
        if ($alternates) {
            $this->writer->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        }

        if ($images) {
            $this->writer->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        }

        if ($videos) {
            $this->writer->writeAttribute('xmlns:video', 'http://www.google.com/schemas/sitemap-video/1.1');
        }

        if ($news) {
            $this->writer->writeAttribute('xmlns:news', 'http://www.google.com/schemas/sitemap-news/0.9');
        }

        return $this;
    }

    public function startIndex(): self
    {
        $this->writer->startDocument('1.0', 'UTF-8');
        $this->writer->setIndent(true);
        $this->writer->startElement('sitemapindex');
        $this->writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return $this;
    }

    public function writeUrl(SitemapUrl $url): self
    {
        $this->writer->startElement('url');
        $this->writer->writeElement('loc', $url->loc);

        if ($url->lastModified !== null) {
            $this->writer->writeElement('lastmod', $url->lastModified->format(DateTimeInterface::ATOM));
        }

        if ($url->changeFrequency !== null) {
            $this->writer->writeElement('changefreq', $url->changeFrequency->value);
        }

        if ($url->priority !== null) {
            $this->writer->writeElement('priority', number_format($url->priority, 1, '.', ''));
        }

        if ($this->usesAlternates) {
            foreach ($url->alternates as $locale => $href) {
                $this->writer->startElement('xhtml:link');
                $this->writer->writeAttribute('rel', 'alternate');
                $this->writer->writeAttribute('hreflang', (string) $locale);
                $this->writer->writeAttribute('href', $href);
                $this->writer->endElement();
            }
        }

        if ($this->usesImages) {
            foreach ($url->images as $image) {
                $this->writer->startElement('image:image');
                $this->writer->writeElement('image:loc', $image);
                $this->writer->endElement();
            }
        }

        if ($this->usesVideos) {
            foreach ($url->videos as $video) {
                $this->writeVideo($video);
            }
        }

        if ($this->usesNews && $url->news !== null) {
            $this->writeNews($url->news);
        }

        $this->writer->endElement();

        $this->count++;

        // Flushing keeps the buffer bounded; without it XMLWriter accumulates
        // the whole document even when writing to a stream.
        $this->writer->flush(false);

        return $this;
    }

    private function writeVideo(SitemapVideo $video): void
    {
        $this->writer->startElement('video:video');
        $this->writer->writeElement('video:thumbnail_loc', $video->thumbnailLoc);
        $this->writer->writeElement('video:title', $video->title);
        $this->writer->writeElement('video:description', $video->description);

        if ($video->contentLoc !== null) {
            $this->writer->writeElement('video:content_loc', $video->contentLoc);
        }

        if ($video->playerLoc !== null) {
            $this->writer->writeElement('video:player_loc', $video->playerLoc);
        }

        if ($video->durationSeconds !== null) {
            $this->writer->writeElement('video:duration', (string) $video->durationSeconds);
        }

        if ($video->publicationDate !== null) {
            $this->writer->writeElement(
                'video:publication_date',
                $video->publicationDate->format(DateTimeInterface::ATOM),
            );
        }

        if ($video->familyFriendly !== null) {
            $this->writer->writeElement('video:family_friendly', $video->familyFriendly ? 'yes' : 'no');
        }

        $this->writer->endElement();
    }

    private function writeNews(SitemapNews $news): void
    {
        $this->writer->startElement('news:news');

        $this->writer->startElement('news:publication');
        $this->writer->writeElement('news:name', $news->publicationName);
        $this->writer->writeElement('news:language', $news->publicationLanguage);
        $this->writer->endElement();

        $this->writer->writeElement('news:publication_date', $news->publicationDate->format(DateTimeInterface::ATOM));
        $this->writer->writeElement('news:title', $news->title);

        if ($news->genres !== null) {
            $this->writer->writeElement('news:genres', $news->genres);
        }

        if ($news->keywords !== null) {
            $this->writer->writeElement('news:keywords', $news->keywords);
        }

        $this->writer->endElement();
    }

    public function writeSitemapReference(string $loc, ?DateTimeInterface $lastModified = null): self
    {
        $this->writer->startElement('sitemap');
        $this->writer->writeElement('loc', $loc);

        if ($lastModified !== null) {
            $this->writer->writeElement('lastmod', $lastModified->format(DateTimeInterface::ATOM));
        }

        $this->writer->endElement();
        $this->writer->flush(false);

        return $this;
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * Close the document. Returns the XML when writing to memory.
     */
    public function finish(): string
    {
        $this->writer->endElement();
        $this->writer->endDocument();

        return (string) $this->writer->flush(true);
    }
}
