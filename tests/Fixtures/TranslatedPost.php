<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Fixtures;

use Duxbo\Seo\Concerns\HasSeo;
use Duxbo\Seo\Contracts\HasAlternateLocales;
use Duxbo\Seo\Contracts\Seoable;
use Illuminate\Database\Eloquent\Model;

/**
 * A model that knows its own translation coverage, rather than leaving
 * AlternateLocaleResolver to infer it from stored seo_meta rows.
 *
 * @property string $name
 * @property string $slug
 */
final class TranslatedPost extends Model implements HasAlternateLocales, Seoable
{
    use HasSeo;

    protected $table = 'posts';

    protected $guarded = [];

    /** @var list<string> */
    public static array $alternateLocales = ['vi', 'en'];

    public function seoUrl(): string
    {
        return 'https://trangcuatoi.vn/bai-viet/'.$this->slug;
    }

    /**
     * @return list<string>
     */
    public function seoAlternateLocales(): array
    {
        return self::$alternateLocales;
    }
}
