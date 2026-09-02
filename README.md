# laravel-seo

Headless SEO toolkit for Laravel 9 – 13.

Meta and Open Graph, schema.org JSON-LD, sitemaps, redirects, 404 monitoring,
content analysis (including Vietnamese) and optional AI assistance — with a
REST API so the admin UI can be Blade, React, Vue, Next, or nothing at all.

**No third-party runtime dependencies.** Only `illuminate/*` and PHP extensions
that ship with every build. Nothing here can be broken by a library someone else
stops maintaining.

## Status

Under construction, but **usable from M2 onward**: add the trait to a model and
meta tags render, with a fallback chain behind them.

```php
class Post extends Model implements Seoable
{
    use HasSeo;

    protected array $seoMap = [
        'title'       => 'name',
        'description' => 'excerpt',
        'og.image'    => 'cover_url',
    ];
}
```

```blade
{{-- in your layout --}}
{!! $post->seoTags() !!}
```

Nothing stored? The mapping above answers. No mapping? The per-model template
does. No template? The site default. Store a value later and it wins, without
anything else changing.

Load a whole index page without a query per record:

```php
$posts = Post::query()->withSeo()->paginate();
```

| Milestone | Scope | State |
|---|---|---|
| M1 | Contracts, data objects, Compat | done |
| M2 | Storage, `HasSeo` trait, resolution pipeline, HTML output | done |
| M3 | schema.org `@graph` | |
| M4 | Sitemap, robots.txt | |
| M5 | Redirects, 404 monitor | |
| M6 | HTTP API, headless formatters | |
| M7 | Content analysis | |
| M8 | AI drivers | |
| M9 | `@duxbo/seo-core` npm package | |
| M10 | Docs, full CI matrix, 1.0 | |

## Requirements

| | |
|---|---|
| PHP | 8.1 – 8.5 |
| Laravel | 9, 10, 11, 12, 13 |
| Extensions | `dom`, `json`, `libxml` (`intl` optional, with a fallback) |

PHP 8.1 rather than 8.0 buys enums, readonly properties, `never`, first-class
callables and pure intersection types — all of which this package's design
leans on. PHP 8.0 reached end of life in November 2023.

## Design rules

These are the constraints the code is held to, not aspirations.

**Contracts are the public surface.** Everything in `src/Contracts` is what
users may depend on and what only changes in a major release. Everything else
is free to be refactored.

**One context parameter per contract method.** Adding a parameter to an
interface method breaks every implementation someone has written; adding a field
to a DTO breaks nothing. So methods take `SeoContext`, `AnalysisContext`,
`AiRequest` — never a list of scalars.

**All version differences live in `Support/Compat`.** No `version_compare` calls
anywhere else. Scattered version checks are how a package spanning five majors
rots quietly.

**Opinionated defaults, total overrides.** The package works untouched, and
every part of it — including how metadata is stored and how the fallback chain
is ordered — can be replaced by binding a different implementation.

Three things are deliberately not switchable, because each is a real
vulnerability rather than a matter of taste: open-redirect validation, redirect
loop detection, and escaping of logged 404 data.

## Development

```bash
composer install
vendor/bin/phpunit
```

## Licence

MIT.
