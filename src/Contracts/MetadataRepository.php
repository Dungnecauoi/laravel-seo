<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Data\DuplicateMatch;
use Duxbo\Seo\Data\SeoData;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Where entered metadata lives.
 *
 * The default implementation uses a polymorphic table so nobody has to migrate
 * their own tables. Swap the binding for a JSON column, Redis, or a remote CMS
 * and the rest of the package neither knows nor cares.
 */
interface MetadataRepository
{
    /**
     * Stored metadata for one record, or null when nothing was entered.
     *
     * A null `$locale` means the shared, locale-independent record.
     */
    public function find(Seoable $model, ?string $locale = null): ?SeoData;

    /**
     * Load metadata for many records in one query.
     *
     * The reason this exists: a polymorphic table costs a join, and an index
     * page rendering 50 records must not become 51 queries.
     *
     * @param  Collection<int, Seoable>  $models
     * @return Collection<string, SeoData>  Keyed by "{seoType}:{seoKey}".
     */
    public function findMany(Collection $models, ?string $locale = null): Collection;

    public function save(Seoable $model, SeoData $data, ?string $locale = null): void;

    public function delete(Seoable $model, ?string $locale = null): void;

    /**
     * Locales this record has stored metadata for. Drives hreflang output.
     *
     * @return list<string>
     */
    public function locales(Seoable $model): array;

    /**
     * Records of the given class with no metadata yet — the audit query.
     *
     * @param  class-string  $modelClass
     * @return LazyCollection<int, Seoable>
     */
    public function missing(string $modelClass, ?string $locale = null): LazyCollection;

    /**
     * Other records whose stored title exactly matches — the live
     * "this title is already used" warning at save time.
     *
     * Compares the *stored* value only, not what a fallback chain would
     * eventually resolve to; two untitled records that both inherit the same
     * template are real duplicates too, but catching those means resolving
     * every other record, which does not belong on the request path a save
     * blocks on. `seo:duplicates` does that heavier, resolved comparison as
     * an offline audit instead.
     *
     * @return list<DuplicateMatch>
     */
    public function duplicateTitles(Seoable $exclude, string $title, ?string $locale = null): array;

    /**
     * @return list<DuplicateMatch>
     */
    public function duplicateDescriptions(Seoable $exclude, string $description, ?string $locale = null): array;
}
