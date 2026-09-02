<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

/**
 * A thing that can be described to search engines.
 *
 * Usually an Eloquent model using the `HasSeo` trait, but nothing here requires
 * Eloquent — a value object backed by an external CMS satisfies it just as
 * well. There is no base class to extend.
 */
interface Seoable
{
    /**
     * Stable type key used to address this record's metadata.
     *
     * For Eloquent this is the morph class, so a morph map entry keeps stored
     * metadata attached even after the class is renamed or moved.
     */
    public function seoType(): string;

    public function seoKey(): int|string;

    /**
     * Absolute, canonical URL of the page representing this record.
     */
    public function seoUrl(): string;

    /**
     * Values to fall back on when no metadata has been entered.
     *
     * Keys use dot notation matching SeoData: `title`, `description`,
     * `og.image`, `twitter.card`.
     *
     * @return array<string, mixed>
     */
    public function seoDefaults(): array;

    /**
     * Source values for token expansion — `%title%`, `%category%`, and friends.
     *
     * @return array<string, mixed>
     */
    public function seoAttributes(): array;
}
