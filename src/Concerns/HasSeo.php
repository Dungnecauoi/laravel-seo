<?php

declare(strict_types=1);

namespace Duxbo\Seo\Concerns;

use Duxbo\Seo\Contracts\LocaleResolver;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Data\SeoData;
use Duxbo\Seo\Seo;
use Duxbo\Seo\Storage\SeoDataMapper;
use Duxbo\Seo\Storage\SeoMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\HtmlString;

/**
 * Gives an Eloquent model everything the Seoable contract asks for.
 *
 * Every method here is overridable. A model that needs different behaviour
 * declares its own; nothing has to be extended and no base class is imposed.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasSeo
{
    public function seoMeta(): MorphMany
    {
        // The local key is the string accessor below rather than the primary
        // key itself: seoable_id is a text column so UUID models work, and
        // PostgreSQL refuses to compare an integer against varchar.
        return $this->morphMany(SeoMeta::class, 'seoable', 'seoable_type', 'seoable_id', 'seo_key_string');
    }

    public function getSeoKeyStringAttribute(): string
    {
        return (string) $this->getKey();
    }

    /**
     * Eager load metadata for a whole result set.
     *
     * Without this an index page of fifty records issues fifty-one queries.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithSeo(Builder $query, ?string $locale = null): Builder
    {
        return $query->with(['seoMeta' => static function ($relation) use ($locale): void {
            // Both the requested locale and the shared row are needed, since
            // the fallback chain decides between them per record.
            $relation->where(static function ($q) use ($locale): void {
                $q->whereNull('locale');

                if ($locale !== null) {
                    $q->orWhere('locale', $locale);
                }
            });
        }]);
    }

    public function seoType(): string
    {
        // The morph class, so a morph map entry keeps stored metadata attached
        // after the class is renamed or moved.
        return $this->getMorphClass();
    }

    public function seoKey(): int|string
    {
        /** @var int|string $key */
        $key = $this->getKey();

        return $key;
    }

    public function seoUrl(): string
    {
        return app(UrlGenerator::class)->forModel($this);
    }

    /**
     * Fallback values, declared by the model.
     *
     * Override this for full control. Otherwise a `$seoMap` property is read,
     * and failing that the config mapping for this class.
     *
     * @return array<string, mixed>
     */
    public function seoDefaults(): array
    {
        $map = property_exists($this, 'seoMap') && is_array($this->seoMap) ? $this->seoMap : [];

        $values = [];

        foreach ($map as $seoKey => $attribute) {
            if (! is_string($seoKey) || ! is_string($attribute)) {
                continue;
            }

            $value = $this->getAttribute($attribute);

            if ($value !== null && $value !== '') {
                $values[$seoKey] = $value;
            }
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function seoAttributes(): array
    {
        return $this->attributesToArray();
    }

    /**
     * Resolved metadata for this record.
     */
    public function seo(?string $locale = null): SeoData
    {
        return $this->seoContext($locale)->data;
    }

    public function seoContext(?string $locale = null): SeoContext
    {
        // Fill in the current locale here rather than leaving it null: null
        // travels down to the repository meaning "the shared row", which is a
        // different question from "the row for the language we are serving".
        $locale ??= app(LocaleResolver::class)->current();

        $context = SeoContext::for($this, $locale);

        // Hand an eager-loaded row straight to the pipeline so the stored-value
        // stage does not go back to the database for something already loaded.
        if ($this->relationLoaded('seoMeta')) {
            $stored = $this->storedSeoData($locale);

            if ($stored !== null) {
                $context = $context->put('stored_data', $stored);
            }
        }

        return app(Seo::class)->resolve($context);
    }

    /**
     * Meta tags for this record, ready for a Blade layout.
     */
    public function seoTags(?string $locale = null): HtmlString
    {
        return app(Seo::class)->render($this->seoContext($locale));
    }

    /**
     * @param  SeoData|array<string, mixed>  $data
     */
    public function saveSeo(SeoData|array $data, ?string $locale = null): static
    {
        app(Seo::class)->save($this, $data, $locale);

        $this->unsetRelation('seoMeta');

        return $this;
    }

    public function forgetSeo(?string $locale = null): static
    {
        app(Seo::class)->forget($this, $locale);

        $this->unsetRelation('seoMeta');

        return $this;
    }

    /**
     * Pick the best eager-loaded row for a locale, without querying.
     */
    private function storedSeoData(?string $locale): ?SeoData
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, SeoMeta> $rows */
        $rows = $this->getRelation('seoMeta');

        foreach ([$locale, null] as $candidate) {
            $row = $rows->first(static fn (SeoMeta $meta): bool => $meta->locale === $candidate);

            if ($row instanceof SeoMeta) {
                return app(SeoDataMapper::class)->toData([
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
}
