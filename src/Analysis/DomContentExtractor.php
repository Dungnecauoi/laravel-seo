<?php

declare(strict_types=1);

namespace Duxbo\Seo\Analysis;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Duxbo\Seo\Contracts\ContentExtractor;
use Duxbo\Seo\Data\ExtractedContent;
use Duxbo\Seo\Data\Heading;
use Duxbo\Seo\Data\ImageRef;
use Duxbo\Seo\Data\Link;
use Duxbo\Seo\Support\Text;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Reads HTML with DOMDocument.
 *
 * ext-dom ships with every PHP build, so this costs no dependency — and HTML is
 * never parsed with regular expressions here, which breaks on the first
 * attribute containing a bracket.
 */
final class DomContentExtractor implements ContentExtractor
{
    public function __construct(private readonly Config $config)
    {
    }

    public function extract(string $content): ExtractedContent
    {
        if (trim($content) === '') {
            return ExtractedContent::empty();
        }

        $document = new DOMDocument();

        // Malformed markup is the norm in stored content; libxml would
        // otherwise emit warnings for every unclosed tag.
        $previous = libxml_use_internal_errors(true);

        // The meta charset is what makes DOMDocument treat the bytes as UTF-8.
        // Without it Vietnamese text is mangled into Latin-1 on the way in.
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$content.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        return new ExtractedContent(
            plainText: Text::normalize(Text::collapse((string) $document->textContent)),
            headings: $this->headings($xpath),
            links: $this->links($xpath),
            images: $this->images($xpath),
        );
    }

    /**
     * @return list<Heading>
     */
    private function headings(DOMXPath $xpath): array
    {
        $headings = [];

        $nodes = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $headings[] = new Heading(
                level: (int) substr($node->nodeName, 1),
                text: Text::collapse((string) $node->textContent),
            );
        }

        return $headings;
    }

    /**
     * @return list<Link>
     */
    private function links(DOMXPath $xpath): array
    {
        $links = [];
        $nodes = $xpath->query('//a[@href]');

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $href = $node->getAttribute('href');

            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:')) {
                continue;
            }

            $links[] = new Link(
                href: $href,
                text: Text::collapse((string) $node->textContent),
                internal: $this->isInternal($href),
                noFollow: str_contains($node->getAttribute('rel'), 'nofollow'),
            );
        }

        return $links;
    }

    /**
     * @return list<ImageRef>
     */
    private function images(DOMXPath $xpath): array
    {
        $images = [];
        $nodes = $xpath->query('//img');

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $src = $node->getAttribute('src');

            if ($src === '') {
                // Lazy-loaded images keep the real source elsewhere.
                $src = $node->getAttribute('data-src');
            }

            if ($src === '') {
                continue;
            }

            $images[] = new ImageRef(
                src: $src,
                alt: $node->hasAttribute('alt') ? $node->getAttribute('alt') : null,
                title: $node->hasAttribute('title') ? $node->getAttribute('title') : null,
            );
        }

        return $images;
    }

    private function isInternal(string $href): bool
    {
        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return true;
        }

        $host = parse_url($href, PHP_URL_HOST);

        if (! is_string($host)) {
            return true;
        }

        $appHost = parse_url((string) $this->config->get('app.url'), PHP_URL_HOST);

        return is_string($appHost) && strtolower($host) === strtolower($appHost);
    }
}
