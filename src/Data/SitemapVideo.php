<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

/**
 * One `<video:video>` entry, per Google's video sitemap extension.
 *
 * `thumbnailLoc`, `title` and `description` are required by the spec; a
 * video with neither `contentLoc` nor `playerLoc` is not a video Google can
 * actually show anyone, so the constructor refuses to build one without at
 * least one — the same "fail at construction, not at crawl time" choice this
 * package makes elsewhere (a redirect loop, an unsafe canonical).
 */
final class SitemapVideo
{
    public function __construct(
        public readonly string $thumbnailLoc,
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $contentLoc = null,
        public readonly ?string $playerLoc = null,
        public readonly ?int $durationSeconds = null,
        public readonly ?\DateTimeInterface $publicationDate = null,
        public readonly ?bool $familyFriendly = null,
    ) {
        if ($contentLoc === null && $playerLoc === null) {
            throw new \InvalidArgumentException(
                'A video sitemap entry needs contentLoc or playerLoc — Google has nothing to link '
                .'a video result to without one.',
            );
        }
    }
}
