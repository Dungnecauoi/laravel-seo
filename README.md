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

### Structured data

Implement `HasSchema` and return a plain array. Any schema.org type works, not
only the ones this package anticipated — the assembler supplies the `@id`, links
the node to the page and publisher, rewrites dates to ISO 8601, and makes URLs
absolute.

```php
public function seoSchema(SeoContext $context): array
{
    return [
        '@type' => 'Article',
        'headline' => $this->name,
        'datePublished' => $this->published_at,   // any parseable format
        'image' => $this->cover_url,              // may be relative
        'author' => Types::person($this->author_name),
    ];
}
```

`Duxbo\Seo\Schema\Types` covers the shapes whose nesting is easy to get wrong —
a price belongs inside an `Offer`, an FAQ answer is its own node.

`Seo::validateSchema($post)` lists what Google would silently drop the rich
result over.

### Sitemaps and robots.txt

Sources are opt-in, one at a time — nothing is discovered, because auto-
registering every model with the trait would push drafts and soft-deleted rows
onto a public sitemap.

```php
'sitemap' => ['sources' => [
    ['model' => Post::class, 'name' => 'posts', 'scope' => fn ($q) => $q->published()],
    ['pages' => ['/', '/gioi-thieu', '/lien-he']],
]],
```

`/sitemap.xml` and `/robots.txt` serve themselves. Large sites run
`php artisan seo:sitemap` to write static files instead. Sources stream with
`lazyById()` and are written with `XMLWriter`, so a million-row table costs
constant memory.

### Redirects and the 404 monitor

```php
app(RedirectRepository::class)->create('/cu', '/moi');
app(RedirectRepository::class)->create('/blog', '/tin-tuc', type: RedirectMatchType::Prefix);
```

Rules are consulted only once a request has already 404ed, so live routes are
never shadowed and the common path costs nothing. Three checks run at write
time and cannot be switched off: off-site targets, catastrophic regex patterns,
and redirect loops.

### Content analysis

```php
$report = Seo::analyze($html, keyword: 'tối ưu SEO', locale: 'vi');
$report->score;      // 0-100
$report->problems(); // failures and warnings only
```

Checks declare which locales they understand. Flesch and its descendants count
syllables the way English spells them, so they sit out on Vietnamese content
rather than producing a confident number that means nothing. In their place:
sentence length in syllables, `được`/`bị` passive markers, and keyword matching
that normalises Unicode first — "tiếng" has two spellings that look identical
and would not otherwise compare equal.

### Headless

```php
'api' => ['enabled' => true, 'models' => ['post']],
```

```ts
// app/page.tsx — nothing to map by hand
export async function generateMetadata({ params }) {
  const r = await fetch(`${API}/api/seo/v1/resolve?url=${params.slug}&format=next`)
  return r.json()
}
```

Formatters: `html`, `array`, `jsonld`, `next`, `nuxt`, `vue`. The API is
disabled by default and its Gate denies everyone until the application defines
it — an SEO panel can rewrite every title on a site, so forgetting to configure
it must lock the door rather than open it.

| Milestone | Scope | State |
|---|---|---|
| M1 | Contracts, data objects, Compat | done |
| M2 | Storage, `HasSeo` trait, resolution pipeline, HTML output | done |
| M3 | schema.org `@graph` | done |
| M4 | Sitemap, robots.txt | done |
| M5 | Redirects, 404 monitor | done |
| M6 | HTTP API, headless formatters | done |
| M7 | Content analysis | done |
| M8 | AI drivers | next |
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
