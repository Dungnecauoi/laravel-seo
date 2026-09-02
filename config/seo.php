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

    /*
    |--------------------------------------------------------------------------
    | Structured data
    |--------------------------------------------------------------------------
    |
    | Nodes are emitted flat inside one @graph and reference each other by @id,
    | which is how Google reads context between them.
    |
    | Models describe themselves by implementing Duxbo\Seo\Contracts\HasSchema
    | and returning a plain array — any schema.org type at all, not only the
    | ones anticipated here. Duxbo\Seo\Schema\Types offers shorthand for the
    | shapes whose nesting is easy to get wrong.
    |
    */

    'schema' => [

        'enabled' => true,

        // Leaving 'name' null omits the Organization node entirely, and every
        // reference to it is pruned rather than left dangling.
        'organization' => [
            'type' => 'Organization',   // or LocalBusiness
            'name' => null,
            'url' => null,
            'logo' => null,
            'logo_width' => null,
            'logo_height' => null,
            'sameAs' => [],

            // LocalBusiness also wants these.
            // 'telephone'  => null,
            // 'address'    => ['@type' => 'PostalAddress', 'addressLocality' => null],
            // 'priceRange' => null,
        ],

        'website' => [
            // Enables the sitelinks search box. Must contain the placeholder.
            // '/tim-kiem?q={search_term_string}'
            'search_url' => null,
        ],

        // Remove one to drop it, add your own to extend the graph. Order does
        // not matter here; each provider declares its own priority so nodes
        // are registered before anything that references them.
        'providers' => [
            Duxbo\Seo\Schema\Providers\OrganizationProvider::class,
            Duxbo\Seo\Schema\Providers\WebSiteProvider::class,
            Duxbo\Seo\Schema\Providers\PrimaryImageProvider::class,
            Duxbo\Seo\Schema\Providers\BreadcrumbProvider::class,
            Duxbo\Seo\Schema\Providers\WebPageProvider::class,
            Duxbo\Seo\Schema\Providers\ModelSchemaProvider::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap
    |--------------------------------------------------------------------------
    |
    | Sources are opt-in, one at a time, and nothing is discovered. Registering
    | every model with the trait automatically would push drafts, private
    | records and soft-deleted rows onto a public sitemap.
    |
    |   'sources' => [
    |       ['model' => App\Models\Post::class, 'name' => 'posts',
    |        'scope' => fn ($q) => $q->where('published', true),
    |        'changefreq' => 'weekly', 'priority' => 0.8],
    |
    |       ['pages' => ['/', '/gioi-thieu', '/lien-he']],
    |   ],
    |
    */

    'sitemap' => [
        'enabled' => true,

        // Protocol maximum is 50,000; anything higher is clamped.
        'max_urls' => 50000,

        // Seconds. Zero rebuilds on every request.
        'cache_ttl' => 3600,

        'sources' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | robots.txt
    |--------------------------------------------------------------------------
    |
    | Turn this off if the project already serves a static public/robots.txt,
    | rather than having two sources of truth.
    |
    */

    'robots' => [
        'enabled' => true,

        'groups' => [
            '*' => [
                'disallow' => [],
            ],
        ],

        // Extra sitemap URLs to advertise besides this package's own.
        'sitemaps' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    |
    | By default rules are checked only once a request has already 404ed, which
    | costs nothing on the paths that match a real route. Set 'eager' to check
    | before routing instead, which suits a large imported rule set.
    |
    | 'allowed_hosts' is a security control, not a convenience: without it,
    | anyone able to write a redirect rule could turn a trusted URL on your
    | domain into a phishing link. Your own APP_URL host is always allowed.
    |
    */

    'redirects' => [
        'enabled' => true,
        'table' => 'seo_redirects',
        'eager' => false,
        'keep_query' => true,
        'cache_ttl' => 3600,
        'allowed_hosts' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | 404 monitor
    |--------------------------------------------------------------------------
    |
    | Every limit here exists because of crawler traffic, not real visitors: a
    | bot probing for /wp-admin and /.env produces tens of thousands of distinct
    | paths a day.
    |
    */

    'not_found' => [
        'enabled' => true,
        'table' => 'seo_not_found',

        // Oldest and least-hit rows are dropped once the table exceeds this.
        'max_rows' => 10000,

        // Record a fraction of hits on a busy site. 1.0 records everything.
        'sample_rate' => 1.0,

        'exclude' => [
            '#\.(js|css|map|ico|png|jpe?g|gif|svg|woff2?|ttf)$#i',
            '#^/(wp-admin|wp-login|wp-content|xmlrpc)#i',
            '#^/(\.env|\.git|vendor|storage/framework)#i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP API
    |--------------------------------------------------------------------------
    |
    | Disabled by default. This surface writes site-wide metadata and reads the
    | 404 log, so it is opted into rather than exposed by installing.
    |
    | The Gate below denies everyone unless the application defines it. That is
    | deliberate: an SEO panel can rewrite every title on the site and redirect
    | any URL, so the failure mode of forgetting to configure it must be a
    | locked door, not an open one.
    |
    | 'models' is an allowlist of morph aliases the API may address. Without it
    | the endpoints would let a caller enumerate every model by guessing class
    | names.
    |
    */

    'api' => [
        'enabled' => false,
        'prefix' => 'api/seo/v1',
        'middleware' => ['api', 'can:viewSeoPanel'],
        'models' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content analysis
    |--------------------------------------------------------------------------
    |
    | Checks declare which locales they understand and sit out the rest, so the
    | English readability measures never run on Vietnamese content — they would
    | produce a confident number that means nothing.
    |
    | Remove a check to drop it; override a weight to change how much it counts.
    |
    */

    'analysis' => [
        'checks' => [
            Duxbo\Seo\Analysis\Checks\Universal\KeywordInTitle::class,
            Duxbo\Seo\Analysis\Checks\Universal\KeywordInDescription::class,
            Duxbo\Seo\Analysis\Checks\Universal\KeywordInUrl::class,
            Duxbo\Seo\Analysis\Checks\Universal\KeywordInOpening::class,
            Duxbo\Seo\Analysis\Checks\Universal\KeywordInHeadings::class,
            Duxbo\Seo\Analysis\Checks\Universal\KeywordInImageAlt::class,
            Duxbo\Seo\Analysis\Checks\Universal\KeywordDensity::class,
            Duxbo\Seo\Analysis\Checks\Universal\TitleLength::class,
            Duxbo\Seo\Analysis\Checks\Universal\DescriptionLength::class,
            Duxbo\Seo\Analysis\Checks\Universal\ContentLength::class,
            Duxbo\Seo\Analysis\Checks\Universal\InternalLinks::class,
            Duxbo\Seo\Analysis\Checks\Universal\ExternalLinks::class,
            Duxbo\Seo\Analysis\Checks\Universal\ImagesHaveAlt::class,
            Duxbo\Seo\Analysis\Checks\Universal\SingleH1::class,

            Duxbo\Seo\Analysis\Checks\Vi\VietnameseReadability::class,
            Duxbo\Seo\Analysis\Checks\Vi\VietnamesePassiveVoice::class,

            Duxbo\Seo\Analysis\Checks\En\FleschReadingEase::class,
        ],

        // 'keyword-density' => 5
        'weights' => [],
    ],

];
