<?php

declare(strict_types=1);

use Duxbo\Seo\Resolution\Stages\GlobalDefaultStage;
use Duxbo\Seo\Resolution\Stages\ModelAttributeStage;
use Duxbo\Seo\Resolution\Stages\SanitizeStage;
use Duxbo\Seo\Resolution\Stages\StoredValueStage;
use Duxbo\Seo\Resolution\Stages\TemplateStage;
use Duxbo\Seo\Resolution\Stages\TokenExpansionStage;
use Duxbo\Seo\Resolution\Stages\TruncateStage;

/*
|--------------------------------------------------------------------------
| SEO
|--------------------------------------------------------------------------
|
| Every default here is replaceable, and nothing has to be touched for the
| package to work.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Site identity
    |--------------------------------------------------------------------------
    |
    | Used by the %sitename% and %sep% tokens.
    |
    */

    'site_name' => env('SEO_SITE_NAME', env('APP_NAME', 'Laravel')),

    'separator' => env('SEO_SEPARATOR', '-'),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | A polymorphic table, so your own tables need no migration. To store
    | metadata somewhere else entirely, bind your own implementation of
    | Duxbo\Seo\Contracts\MetadataRepository and ignore this section.
    |
    */

    'storage' => [
        'table' => 'seo_meta',
        'connection' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resolution pipeline
    |--------------------------------------------------------------------------
    |
    | The order in which a page's final metadata is decided. Remove a stage,
    | reorder two, or insert your own — an AI fallback, a lookup against an
    | external CMS — without touching package code.
    |
    | The first four stages supply values and never overwrite one already
    | decided. The last three transform whatever the first four produced, so
    | they belong at the end.
    |
    */

    'pipeline' => [
        StoredValueStage::class,
        ModelAttributeStage::class,
        TemplateStage::class,
        GlobalDefaultStage::class,
        TokenExpansionStage::class,
        SanitizeStage::class,
        TruncateStage::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Site-wide defaults
    |--------------------------------------------------------------------------
    |
    | The last stage that can supply a value. Keys are dot-notated, so
    | 'og.image' and 'twitter.card' work alongside 'title'.
    |
    */

    'defaults' => [
        'title' => '%sitename%',
        'description' => null,
        'og.type' => 'website',
        'og.site_name' => env('SEO_SITE_NAME', env('APP_NAME', 'Laravel')),
        'twitter.card' => 'summary_large_image',
    ],

    /*
    |--------------------------------------------------------------------------
    | Indexable environments
    |--------------------------------------------------------------------------
    |
    | Anywhere else, pages default to noindex. Forgetting to switch this on
    | costs a day of traffic; forgetting to switch it off lets staging compete
    | with production in the index for months.
    |
    */

    'indexable_environments' => ['production'],

    /*
    |--------------------------------------------------------------------------
    | Per-model configuration
    |--------------------------------------------------------------------------
    |
    | For models you cannot edit, or when you would rather keep the mapping out
    | of the model. A model's own seoDefaults() method wins over this.
    |
    |   App\Models\Post::class => [
    |       'route'    => ['name' => 'posts.show', 'parameter' => 'post', 'binding' => 'slug'],
    |       'map'      => ['title' => 'name', 'description' => 'excerpt', 'og.image' => 'cover_url'],
    |       'template' => ['title' => '%title% %sep% %sitename%'],
    |   ],
    |
    */

    'models' => [],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | 'supported' drives hreflang; fewer than two locales emits none, since a
    | lone self-referential hreflang tends to get the whole cluster ignored.
    |
    | 'fallbacks' maps a locale to what is tried when it has no stored row.
    | Null means the shared row that applies to every language. A regional
    | locale falls back to its base language automatically.
    |
    */

    'locales' => [
        'supported' => [],

        'fallbacks' => [
            // 'en-GB' => ['en', null],
        ],

        // fn (string $url, string $locale): string
        'alternate_url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Length limits
    |--------------------------------------------------------------------------
    |
    | Titles are measured in pixels, not characters: Google truncates by
    | rendered width, so "iiiii" and "WWWWW" are the same by character count
    | and three times apart on screen. This matters more in Vietnamese, where
    | diacritics add bytes without adding width.
    |
    */

    'limits' => [
        'title_pixels' => 580,
        'description_min' => 120,
        'description_max' => 158,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tokens
    |--------------------------------------------------------------------------
    */

    'tokens' => [
        'date_format' => 'd/m/Y',
    ],

];
