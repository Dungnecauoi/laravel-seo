<?php

declare(strict_types=1);

namespace Duxbo\Seo\Facades;

use Duxbo\Seo\Seo as SeoManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Duxbo\Seo\Data\SeoData for(\Duxbo\Seo\Contracts\Seoable $model, ?string $locale = null)
 * @method static \Duxbo\Seo\Data\SeoData forUrl(string $url, ?string $locale = null)
 * @method static \Duxbo\Seo\Data\SeoContext context(\Duxbo\Seo\Contracts\Seoable $model, ?string $locale = null)
 * @method static \Duxbo\Seo\Data\SeoContext resolve(\Duxbo\Seo\Data\SeoContext $context)
 * @method static \Illuminate\Support\HtmlString render(\Duxbo\Seo\Contracts\Seoable|\Duxbo\Seo\Data\SeoContext|null $subject = null, ?string $locale = null)
 * @method static mixed format(string $formatter, \Duxbo\Seo\Data\SeoContext $context)
 * @method static void save(\Duxbo\Seo\Contracts\Seoable $model, \Duxbo\Seo\Data\SeoData|array $data, ?string $locale = null)
 * @method static void forget(\Duxbo\Seo\Contracts\Seoable $model, ?string $locale = null)
 * @method static SeoManager registerToken(\Duxbo\Seo\Contracts\TokenResolver $resolver)
 * @method static SeoManager removeToken(string $key)
 * @method static SeoManager registerFormatter(\Duxbo\Seo\Contracts\OutputFormatter $formatter)
 * @method static list<string> formatters()
 * @method static \Duxbo\Seo\Resolution\TokenExpander tokens()
 * @method static \Duxbo\Seo\Resolution\Resolver pipeline()
 * @method static \Duxbo\Seo\Contracts\MetadataRepository repository()
 *
 * @see SeoManager
 */
final class Seo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SeoManager::class;
    }
}
