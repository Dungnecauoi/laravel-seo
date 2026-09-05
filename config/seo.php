<?php

declare(strict_types=1);

use Duxbo\Seo\Resolution\Stages\GlobalDefaultStage;
use Duxbo\Seo\Resolution\Stages\ModelAttributeStage;
use Duxbo\Seo\Resolution\Stages\SanitizeStage;
use Duxbo\Seo\Resolution\Stages\StoredValueStage;
use Duxbo\Seo\Resolution\Stages\TemplateStage;
use Duxbo\Seo\Resolution\Stages\TokenExpansionStage;
use Duxbo\Seo\Resolution\Stages\TruncateStage;
use Duxbo\Seo\Settings\Validators\BooleanSettingValidator;
use Duxbo\Seo\Settings\Validators\IndexNowKeyValidator;
use Duxbo\Seo\Settings\Validators\SearchConsoleCredentialValidator;
use Duxbo\Seo\Settings\Validators\StringSettingValidator;
use Duxbo\Seo\Settings\Validators\TwitterCardSettingValidator;
use Duxbo\Seo\Settings\Validators\UrlListSettingValidator;
use Duxbo\Seo\Settings\Validators\UrlSettingValidator;

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
    | Master switch
    |--------------------------------------------------------------------------
    |
    | The safety net for a demo domain shown to a client before launch, or any
    | environment that must never be indexed no matter what a content editor
    | does on an individual page: set this to false (SEO_ENABLED=false) and
    | every page forces noindex,nofollow — a stored per-page "index" cannot
    | override it — robots.txt disallows everything, and the sitemap goes
    | empty. Listing URLs a crawler has just been told not to index is its
    | own contradiction, the same class of mistake as a stale robots.txt.
    |
    | This is stronger than, and separate from, 'indexable_environments'
    | below: that one is a *default* a stored value is still allowed to beat,
    | useful for testing SEO behaviour on staging. This one is not — it means
    | "not this domain", regardless of environment or per-page overrides.
    |
    | Meta tags, Open Graph, canonical links and structured data still render
    | as normal — a demo link shared in Slack should still preview nicely.
    | Only what governs whether search engines index the page is affected.
    |
    */

    'enabled' => env('SEO_ENABLED', true),

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

        // Google's own recommendation for publishers who want the most
        // traffic from image-based results and Discover — without it, Google
        // shows a smaller preview by default. A stored per-page robots value
        // still overrides this like any other default; set null here to opt
        // the whole site out.
        'robots' => 'max-image-preview:large',
    ],

    /*
    |--------------------------------------------------------------------------
    | Search engine verification
    |--------------------------------------------------------------------------
    |
    | Paste the code each console gives you when adding this property — not
    | the whole HTML snippet, just the value of its content attribute. Emitted
    | as a <meta> tag on every page (Google, Bing and Yandex only check the
    | home page, but a mismatch between which page carries it and which page
    | the crawler samples is not worth guarding against). Leave unset and
    | nothing is emitted for that console.
    |
    */

    'verification' => [
        'google' => env('SEO_VERIFY_GOOGLE'),
        'bing' => env('SEO_VERIFY_BING'),
        'yandex' => env('SEO_VERIFY_YANDEX'),
        'pinterest' => env('SEO_VERIFY_PINTEREST'),
        'facebook' => env('SEO_VERIFY_FACEBOOK'),
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

        // Separate from 'groups' above: this blocks AI training crawlers
        // specifically, while leaving Googlebot, Bingbot and the rest of
        // '*' untouched — the two are different decisions ("can this be
        // searched" vs. "can this be used to train a model") and a project
        // that wants one without the other should not have to hand-list
        // every bot user-agent itself.
        'block_ai_crawlers' => env('SEO_BLOCK_AI_CRAWLERS', false),

        'ai_crawlers' => [
            'GPTBot',            // OpenAI — training
            'ChatGPT-User',      // OpenAI — plugin/browsing on a user's behalf
            'Google-Extended',   // Google — Gemini/Vertex training, separate from Googlebot
            'ClaudeBot',         // Anthropic — training
            'Claude-Web',        // Anthropic — browsing on a user's behalf
            'anthropic-ai',
            'CCBot',             // Common Crawl — dataset behind many third-party models
            'PerplexityBot',
            'Applebot-Extended', // Apple Intelligence training, separate from Applebot
            'Bytespider',        // ByteDance
            'Amazonbot',
            'Diffbot',
            'FacebookBot',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IndexNow
    |--------------------------------------------------------------------------
    |
    | Bing, Yandex and Seznam pick up a changed URL almost immediately instead
    | of waiting for their next crawl. Google does not participate in
    | IndexNow — for it, a submitted sitemap is still the only real signal.
    |
    | Off by default: this makes an outbound HTTP request, and installing a
    | package must never start one on its own. `key` doubles as the filename
    | (`{key}.txt`) this package serves at the site root so IndexNow can
    | confirm the submission actually came from whoever owns the domain — it
    | must stay the same between deploys, so generate it once and keep it in
    | .env rather than regenerating on every submission.
    |
    */

    'indexnow' => [
        'enabled' => env('SEO_INDEXNOW_ENABLED', false),
        'key' => env('SEO_INDEXNOW_KEY'),
        'endpoint' => 'https://api.indexnow.org/indexnow',

        // One row per API call, not per URL — "did this submission go
        // through" is the question this answers, not a full history of
        // every individual URL ever pushed.
        'log' => true,
        'log_table' => 'seo_indexnow_log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit history
    |--------------------------------------------------------------------------
    |
    | `php artisan seo:audit` writes here — one seo_audit_batches row per run,
    | one seo_audits row per record it scored. `seo_meta` only ever holds the
    | latest state, never a trend line, so a dashboard charting "average score
    | over the last 90 days" has nowhere else to read that from. Nothing
    | schedules this on its own: an audit needs the model's actual body
    | content to score readability and keyword usage, and only the
    | application knows which attribute holds that.
    |
    */

    'audit' => [
        'batches_table' => 'seo_audit_batches',
        'audits_table' => 'seo_audits',
    ],

    /*
    |--------------------------------------------------------------------------
    | Internal link graph
    |--------------------------------------------------------------------------
    |
    | `php artisan seo:internal-links` writes here — which pages a model's own
    | content links to, so an orphan (a page nothing else links to) can be
    | found without a manual click-through of the whole site. Every crawl of
    | one record replaces its rows outright rather than diffing them, so
    | removing a link from the content removes it here too on the next run.
    |
    */

    'internal_links' => [
        'table' => 'seo_internal_links',
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Console
    |--------------------------------------------------------------------------
    |
    | Free, unlike keyword rank tracking — but it only ever reports on pages
    | Google has already indexed and shown in a real result, not an arbitrary
    | keyword picked in advance, which is the actual difference between this
    | and a paid SERP-tracking service.
    |
    | Needs a one-time manual setup this package cannot do for a project: a
    | Google Cloud project with the Search Console API enabled, an OAuth
    | client (Credentials → OAuth client ID → Desktop app), and a refresh
    | token obtained once by sending that client through the consent screen
    | yourself — the package only ever uses the refresh token afterward, it
    | never runs the consent flow itself. `site_url` is the exact property
    | string Search Console shows for the verified property, e.g.
    | 'https://trangcuatoi.vn/' or 'sc-domain:trangcuatoi.vn'.
    |
    */

    'search_console' => [
        'enabled' => env('SEO_SEARCH_CONSOLE_ENABLED', false),
        'client_id' => env('SEO_SEARCH_CONSOLE_CLIENT_ID'),
        'client_secret' => env('SEO_SEARCH_CONSOLE_CLIENT_SECRET'),
        'refresh_token' => env('SEO_SEARCH_CONSOLE_REFRESH_TOKEN'),
        'site_url' => env('SEO_SEARCH_CONSOLE_SITE_URL'),
        'table' => 'seo_search_console_stats',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic settings
    |--------------------------------------------------------------------------
    |
    | Everything in this file is read at every boot regardless — a stored
    | override just wins over the value written here, applied before
    | anything else in the package reads a single seo.* key. Off by default:
    | this file is the only source of truth until a project opts in, so
    | installing the package never depends on a migration having run yet.
    |
    | Only the dot-notated keys listed here can ever be written through
    | `SettingsRepository::set()` or the REST API — the same reasoning
    | behind `seo.api.models` allowlisting which model types the API can
    | touch, rather than accepting any key a caller names. Keys that gate
    | route registration at boot (`api.enabled`, `panel.enabled`,
    | `indexnow.enabled` alongside its key) still only take effect on the
    | next full boot in a long-running process (Octane, a queue worker) —
    | in the ordinary PHP-FPM request lifecycle this file re-reads on every
    | request anyway, so there is nothing to wait for there.
    |
    */

    'settings' => [
        'enabled' => env('SEO_DYNAMIC_SETTINGS_ENABLED', false),
        'table' => 'seo_settings',
        'cache_ttl' => 60,

        'keys' => [
            'enabled',
            'site_name',
            'defaults.title',
            'defaults.description',
            'defaults.robots',
            'defaults.og.site_name',
            'defaults.twitter.card',
            'verification.google',
            'verification.bing',
            'verification.yandex',
            'verification.pinterest',
            'verification.facebook',
            'robots.block_ai_crawlers',
            'schema.organization.name',
            'schema.organization.logo',
            'schema.organization.sameAs',
            'schema.website.search_url',
            'indexnow.enabled',
            'indexnow.key',
            'search_console.enabled',
            'search_console.client_id',
            'search_console.client_secret',
            'search_console.refresh_token',
            'search_console.site_url',
        ],

        // Still writable through the same PUT — this only changes what GET
        // ever echoes back. A client secret and a refresh token grant
        // access to Google on this site's behalf; unlike indexnow.key
        // (published at /{key}.txt on purpose) or client_id (routinely
        // visible in a browser's own OAuth redirect), neither has any
        // legitimate reason to be readable again once it is set.
        'secret_keys' => [
            'search_console.client_secret',
            'search_console.refresh_token',
        ],

        // One validator per key above, checking shape rather than just
        // presence — the allowlist only ever proved a key was *expected*,
        // never that a written value was safe to push into live config.
        // tests/Feature/SettingsAllowlistHasValidatorsTest.php fails the
        // build if a key is added above without a matching entry here.
        'validators' => [
            'enabled' => BooleanSettingValidator::class,
            'site_name' => StringSettingValidator::class,
            'defaults.title' => StringSettingValidator::class,
            'defaults.description' => StringSettingValidator::class,
            'defaults.robots' => StringSettingValidator::class,
            'defaults.og.site_name' => StringSettingValidator::class,
            'defaults.twitter.card' => TwitterCardSettingValidator::class,
            'verification.google' => StringSettingValidator::class,
            'verification.bing' => StringSettingValidator::class,
            'verification.yandex' => StringSettingValidator::class,
            'verification.pinterest' => StringSettingValidator::class,
            'verification.facebook' => StringSettingValidator::class,
            'robots.block_ai_crawlers' => BooleanSettingValidator::class,
            'schema.organization.name' => StringSettingValidator::class,
            'schema.organization.logo' => UrlSettingValidator::class,
            'schema.organization.sameAs' => UrlListSettingValidator::class,
            'schema.website.search_url' => UrlSettingValidator::class,
            'indexnow.enabled' => BooleanSettingValidator::class,
            'indexnow.key' => IndexNowKeyValidator::class,
            'search_console.enabled' => BooleanSettingValidator::class,
            'search_console.client_id' => StringSettingValidator::class,
            'search_console.client_secret' => SearchConsoleCredentialValidator::class,
            'search_console.refresh_token' => SearchConsoleCredentialValidator::class,
            'search_console.site_url' => UrlSettingValidator::class,
        ],
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
    | AI assistance
    |--------------------------------------------------------------------------
    |
    | Off by default: installing a package must never start billing anyone.
    | Set 'default' to claude, openai, gemini, groq or openrouter once a key
    | is configured.
    |
    | Drivers call plain documented REST through Laravel's HTTP client. No
    | vendor SDK is required or suggested — three SDKs would be three more
    | libraries whose next major becomes this package's problem, for a feature
    | that is off by default.
    |
    | Model names live here rather than in the drivers. They change often, and
    | a hard-coded one turns into a maintenance task every few months.
    |
    */

    'ai' => [
        'default' => env('SEO_AI_DRIVER', 'null'),

        'drivers' => [
            'claude' => [
                'key' => env('ANTHROPIC_API_KEY'),
                'model' => env('SEO_AI_MODEL', 'claude-sonnet-5'),
                'timeout' => 30,
                'retries' => 2,
            ],

            'openai' => [
                'key' => env('OPENAI_API_KEY'),
                'model' => env('SEO_AI_MODEL'),
                'base_url' => 'https://api.openai.com/v1',
                'timeout' => 30,
                'retries' => 2,
            ],

            'gemini' => [
                'key' => env('GEMINI_API_KEY'),
                'model' => env('SEO_AI_MODEL'),
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'timeout' => 30,
                'retries' => 2,
            ],

            'groq' => [
                'key' => env('GROQ_API_KEY'),
                'model' => env('SEO_AI_MODEL'),
                'base_url' => 'https://api.groq.com/openai/v1',
                'timeout' => 30,
                'retries' => 2,
            ],

            'openrouter' => [
                'key' => env('OPENROUTER_API_KEY'),
                'model' => env('SEO_AI_MODEL'),
                'base_url' => 'https://openrouter.ai/api/v1',
                'timeout' => 30,
                'retries' => 2,
                // Optional — OpenRouter's own docs ask for these for its
                // public leaderboard attribution, not for the request to work.
                'referer' => env('APP_URL'),
                'title' => env('SEO_SITE_NAME', env('APP_NAME')),
            ],
        ],

        // Same content and prompt is never billed twice. Seconds; 0 disables.
        'cache_ttl' => 86400,

        // A loop over a few thousand records is an ordinary thing to write and
        // an expensive thing to run. 0 removes the cap.
        'daily_token_budget' => 200000,

        'log' => true,
        'table' => 'seo_ai_log',

        // Markup is noise the model pays for by the token.
        'content_characters' => 4000,

        // Published prices change; a hard-coded rate would make the cost column
        // quietly wrong. Rates are per million tokens.
        'pricing' => [
            'currency' => 'USD',
            'models' => [
                // 'claude-sonnet-5' => ['input' => 3.00, 'output' => 15.00],
            ],
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
    | Blade admin panel
    |--------------------------------------------------------------------------
    |
    | A working meta editor at /seo/panel/{type}/{id}, published views included
    | — disabled by default and behind the same viewSeoPanel Gate as the API.
    |
    | Its fetch calls go through `web` middleware (session + CSRF), not the
    | token auth the REST API above expects: a same-origin admin page already
    | has both, and routing through bearer tokens would mean standing up
    | Sanctum just for this page. Set the SAME model types in seo.api.models —
    | the panel and the API share one allowlist, so a type must be exposed
    | there regardless of which surface serves the page.
    |
    | React, Vue, or nothing at all also work: @duxbo/seo-react and
    | @duxbo/seo-vue are separate npm packages built on the REST API instead,
    | for a project that already has a front-end build step.
    |
    */

    'panel' => [
        'enabled' => false,
        'prefix' => 'seo/panel',
        'middleware' => ['web', 'can:viewSeoPanel'],
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

        // ContentLength's own $minimum (600) is syllables, tuned for
        // Vietnamese and English — both mark word boundaries with
        // whitespace. Chinese, Japanese and Thai do not, so the same check
        // measures those in *letters* instead once it detects one of those
        // scripts (see Text::isSpaceDelimitedScript()), and needs a
        // differently-scaled threshold for that unit. There is no
        // established word-to-character conversion this number is derived
        // from — like Analysis\Vietnamese's own readability measures, this
        // is a heuristic, not a validated instrument, and worth tuning for
        // whichever of those languages a real site actually publishes in.
        'content_length_cjk_minimum' => 800,

        /*
        | Analysis runs real work per request — DOMDocument parsing plus every
        | check above — with no cost control the way the AI budget has one.
        | Both the REST API and the Blade panel's own /analyze route already
        | sit behind the viewSeoPanel Gate, so this is defense in depth against
        | a buggy or malicious authenticated client hammering it, not a public
        | surface. Laravel's throttle middleware syntax: "attempts,minutes".
        */
        'rate_limit' => '30,1',
    ],

];
