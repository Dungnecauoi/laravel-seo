<?php

declare(strict_types=1);

namespace Duxbo\Seo\Storage;

use Duxbo\Seo\Contracts\LocaleResolver;
use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Data\DuplicateMatch;
use Duxbo\Seo\Data\SeoData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Default storage: one polymorphic table, so nobody has to migrate their own.
 *
 * The join this costs is why {@see findMany()} exists and why the trait ships
 * a `withSeo()` scope — an index page rendering fifty records must not turn
 * into fifty-one queries.
 */
final class EloquentMetadataRepository implements MetadataRepository
{
    public function __construct(
        private readonly SeoDataMapper $mapper,
        private readonly LocaleResolver $locales,
    ) {
    }

    public function find(Seoable $model, ?string $locale = null): ?SeoData
    {
        $rows = $this->rowsFor($model)->get();

        return $this->pickForLocale($rows, $locale);
    }

    public function findMany(Collection $models, ?string $locale = null): Collection
    {
        if ($models->isEmpty()) {
            return new Collection();
        }

        $query = SeoMeta::query();

        foreach ($models->groupBy(static fn (Seoable $m): string => $m->seoType()) as $type => $group) {
            $ids = $group
                ->map(static fn (Seoable $m): string => (string) $m->seoKey())
                ->unique()
                ->values()
                ->all();

            $query->orWhere(static function (Builder $q) use ($type, $ids): void {
                $q->where('seoable_type', $type)->whereIn('seoable_id', $ids);
            });
        }

        $rows = $query->get()->groupBy(
            static fn (SeoMeta $row): string => $row->seoable_type.':'.$row->seoable_id,
        );

        $results = new Collection();

        foreach ($models as $model) {
            $key = $this->cacheKey($model);
            /** @var Collection<int, SeoMeta> $group */
            $group = $rows->get($key) ?? new Collection();
            $data = $this->pickForLocale($group, $locale);

            if ($data !== null) {
                $results->put($key, $data);
            }
        }

        return $results;
    }

    public function save(Seoable $model, SeoData $data, ?string $locale = null): void
    {
        SeoMeta::query()->updateOrCreate(
            [
                'seoable_type' => $model->seoType(),
                'seoable_id' => (string) $model->seoKey(),
                'locale' => $locale,
            ],
            $this->mapper->toRow($data),
        );
    }

    /**
     * Deletes exactly one record.
     *
     * A null locale addresses the shared row specifically — it must not be read
     * as "all locales" and take the translations with it.
     */
    public function delete(Seoable $model, ?string $locale = null): void
    {
        $this->rowsFor($model)
            ->when(
                $locale === null,
                static fn (Builder $q) => $q->whereNull('locale'),
                static fn (Builder $q) => $q->where('locale', $locale),
            )
            ->delete();
    }

    public function locales(Seoable $model): array
    {
        /** @var list<string> $locales */
        $locales = $this->rowsFor($model)
            ->whereNotNull('locale')
            ->orderBy('locale')
            ->pluck('locale')
            ->all();

        return $locales;
    }

    public function missing(string $modelClass, ?string $locale = null): LazyCollection
    {
        /** @var Model&Seoable $probe */
        $probe = new $modelClass();

        $meta = new SeoMeta();
        $metaTable = $meta->getTable();
        $connection = $meta->getConnection();
        $grammar = $connection->getQueryGrammar();

        // The model's key is usually an integer while seoable_id is text.
        // MySQL coerces silently, PostgreSQL refuses — so cast explicitly, and
        // wrap both sides through the grammar rather than interpolating names.
        $left = $grammar->wrap($metaTable.'.seoable_id');
        $right = $this->castToText(
            $grammar->wrap($probe->getTable().'.'.$probe->getKeyName()),
            $connection->getDriverName(),
        );

        return $probe->newQuery()
            ->whereNotExists(function ($query) use ($metaTable, $left, $right, $probe, $locale): void {
                $query->selectRaw('1')
                    ->from($metaTable)
                    ->whereRaw("{$left} = {$right}")
                    ->where($metaTable.'.seoable_type', $probe->getMorphClass());

                $locale === null
                    ? $query->whereNull($metaTable.'.locale')
                    : $query->where($metaTable.'.locale', $locale);
            })
            ->lazyById();
    }

    public function duplicateTitles(Seoable $exclude, string $title, ?string $locale = null): array
    {
        return $this->duplicatesOf('title', $exclude, $title, $locale);
    }

    public function duplicateDescriptions(Seoable $exclude, string $description, ?string $locale = null): array
    {
        return $this->duplicatesOf('description', $exclude, $description, $locale);
    }

    /**
     * @return list<DuplicateMatch>
     */
    private function duplicatesOf(string $column, Seoable $exclude, string $value, ?string $locale): array
    {
        if (trim($value) === '') {
            return [];
        }

        $rows = SeoMeta::query()
            ->where($column, $value)
            ->when(
                $locale === null,
                static fn (Builder $q) => $q->whereNull('locale'),
                static fn (Builder $q) => $q->where('locale', $locale),
            )
            // NOT (seoable_type = X AND seoable_id = Y), so this excludes only
            // the record being checked, not merely a record that shares one
            // of the two fields.
            ->where(function (Builder $q) use ($exclude): void {
                $q->where('seoable_type', '!=', $exclude->seoType())
                    ->orWhere('seoable_id', '!=', (string) $exclude->seoKey());
            })
            ->limit(20)
            ->get(['seoable_type', 'seoable_id', 'locale']);

        return $rows
            ->map(static fn (SeoMeta $row): DuplicateMatch => new DuplicateMatch(
                $row->seoable_type,
                $row->seoable_id,
                $row->locale,
            ))
            ->all();
    }

    /**
     * @return Builder<SeoMeta>
     */
    private function rowsFor(Seoable $model): Builder
    {
        return SeoMeta::query()
            ->where('seoable_type', $model->seoType())
            ->where('seoable_id', (string) $model->seoKey());
    }

    private function cacheKey(Seoable $model): string
    {
        return $model->seoType().':'.$model->seoKey();
    }

    /**
     * Walk the locale fallback chain and return the first row that exists.
     *
     * @param  Collection<int, SeoMeta>  $rows
     */
    private function pickForLocale(Collection $rows, ?string $locale): ?SeoData
    {
        if ($rows->isEmpty()) {
            return null;
        }

        foreach ($this->locales->fallbackChain($locale) as $candidate) {
            $row = $rows->first(static fn (SeoMeta $meta): bool => $meta->locale === $candidate);

            if ($row instanceof SeoMeta) {
                return $this->mapper->toData([
                    'title' => $row->title,
                    'description' => $row->description,
                    'canonical_url' => $row->canonical_url,
                    'robots' => $row->robots,
                    'og' => $row->og,
                    'twitter' => $row->twitter,
                    'focus_keyword' => $row->focus_keyword,
                    'secondary_keywords' => $row->secondary_keywords,
                    'score' => $row->score,
                    'extra' => $row->extra,
                ]);
            }
        }

        return null;
    }

    private function castToText(string $wrappedColumn, string $driver): string
    {
        return match ($driver) {
            'pgsql' => "{$wrappedColumn}::text",
            'sqlsrv' => "CAST({$wrappedColumn} AS NVARCHAR(64))",
            default => "CAST({$wrappedColumn} AS CHAR(64))",
        };
    }
}
