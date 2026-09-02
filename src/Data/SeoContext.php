<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Data\Concerns\Copyable;

/**
 * Everything the package knows about the page currently being described.
 *
 * This object is the reason contract methods take one parameter instead of
 * several. Adding a field here is a minor release; adding a parameter to an
 * interface method would break every implementation a user has written.
 */
final class SeoContext
{
    use Copyable;

    /**
     * @param  array<string, mixed>  $attributes  Source values for mapping and token expansion.
     * @param  array<string, mixed>  $bag  Scratch space for pipeline stages to pass state along.
     */
    public function __construct(
        public readonly string $url,
        public readonly ?string $locale = null,
        public readonly ?Seoable $model = null,
        public readonly SeoData $data = new SeoData(),
        public readonly array $attributes = [],
        public readonly array $bag = [],
    ) {
    }

    public static function for(Seoable $model, ?string $locale = null): self
    {
        return new self(
            url: $model->seoUrl(),
            locale: $locale,
            model: $model,
            attributes: $model->seoAttributes(),
        );
    }

    public static function forUrl(string $url, ?string $locale = null): self
    {
        return new self(url: $url, locale: $locale);
    }

    public function hasModel(): bool
    {
        return $this->model !== null;
    }

    public function withData(SeoData $data): self
    {
        return $this->with(data: $data);
    }

    /**
     * Merge candidate values in without overwriting anything already decided.
     */
    public function fillMissing(SeoData $fallback): self
    {
        return $this->with(data: $this->data->fillMissingFrom($fallback));
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->bag[$key] ?? $default;
    }

    public function put(string $key, mixed $value): self
    {
        return $this->with(bag: array_merge($this->bag, [$key => $value]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function constructorArgs(): array
    {
        return [
            'url' => $this->url,
            'locale' => $this->locale,
            'model' => $this->model,
            'data' => $this->data,
            'attributes' => $this->attributes,
            'bag' => $this->bag,
        ];
    }
}
