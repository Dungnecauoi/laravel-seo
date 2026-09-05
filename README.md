# laravel-seo

Headless SEO toolkit for Laravel 12 and 13.

Meta and Open Graph, schema.org JSON-LD, sitemaps, redirects, 404 monitoring,
content analysis (including Vietnamese) and optional AI assistance — with a
REST API so the admin UI can be Blade, React, Vue, Next, or nothing at all.

**No third-party runtime dependencies.** Only `illuminate/*` and PHP extensions
that ship with every build. Nothing here can be broken by a library someone else
stops maintaining.

## Status

Feature-complete at **0.9**, and not yet 1.0 on purpose: nothing here has run in
a production site, and that is the only thing that turns a well-built package
into a hardened one. `Contracts/` stays open until it has.

**Usable from M2 onward**: add the trait to a model and
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

`og.type = 'article'` unlocks `article:published_time`, `article:modified_time`,
`article:author`, `article:section` and `article:tag` — set via
`'og.publishedTime' => …` alongside the rest of `$seoMap`. They render only
under `article`; the Open Graph spec ties them to that type, and every real
consumer (Facebook, LinkedIn) ignores them on a `website` page regardless.

`seo.defaults.robots` defaults to `max-image-preview:large` — Google's own
recommendation for the most traffic from image results and Discover. A stored
per-page robots value overrides it like any other default; set the config key
to `null` to opt the whole site out.

### Demo domains — the master switch

```env
SEO_ENABLED=false
```

For a client-preview domain that must never reach the index, no matter what a
content editor does on an individual page. Every page forces `noindex,nofollow`
— a stored per-page override cannot beat it — `robots.txt` disallows
everything, and the sitemap goes empty. Meta tags, Open Graph and canonical
links still render, so a link shared in Slack still previews properly; only
what governs indexing is affected.

This is stronger than, and separate from, `seo.indexable_environments`: that
one is a *default* a stored per-page value is still allowed to beat, useful
for testing SEO behaviour on staging. `SEO_ENABLED=false` means "not this
domain" regardless of environment or per-page overrides.

### Hreflang and translation coverage

Nothing is guessed. A model implements `HasAlternateLocales` to declare which
locales it genuinely has content in:

```php
public function seoAlternateLocales(): array
{
    return ['vi', 'en'];   // only the ones this record actually has
}
```

Without it, only locales with their own stored SEO row count as evidence.
Assuming every record exists in every site-wide supported locale is exactly
how a partially-translated site used to end up with `hreflang="en"` pointing
at a page that 404s — and Google does not just ignore that one broken link,
it can discard the whole hreflang cluster over it. The same resolver backs
`hreflang` in HTML, the `languages` field in the Next.js formatter, and
`<xhtml:link>` alternates in the sitemap, so all three agree.

Every alternate comes from the *same* record's own URL, just asked for in
another locale — there is no separate translation row that could point back
the wrong way, so the classic "hreflang isn't reciprocal" bug cannot occur by
construction here. What a misconfigured `locale_parameter` mapping or a
custom `seo.locales.alternate_url` resolver *can* still do is produce the
same URL for two different locales — Google discards the whole cluster over
that too. `php artisan seo:hreflang {model}` checks every record for it.

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

The three site-wide nodes — `Organization`, `WebSite`, `WebPage` — pass
through any `seo.schema.organization.*` / `seo.schema.website.*` config key
they do not already build themselves, so `geo`, `foundingDate`,
`areaServed`, or any other schema.org property just works without this
package needing to know its name in advance:

```php
'schema' => ['organization' => [
    'name' => 'Công Ty Của Tôi',
    'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 21.03, 'longitude' => 105.85],
]],
```

Breadcrumbs are separate — implement `HasBreadcrumbs` when the model knows its
own trail, since not every model does:

```php
public function seoBreadcrumbs(): array
{
    return [['Trang chủ' => '/'], ['name' => 'Tin tức', 'url' => '/tin-tuc'], $this->name];
}
```

A product reached through two different category paths has two different
trails, which a model alone cannot always resolve — set `breadcrumbs` on the
`SeoContext` from the controller instead when that is the case.

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

A record whose *stored* metadata marks it `noindex` is skipped automatically —
listing a URL a crawler has just been told not to index is the same
contradiction as a stale `robots.txt`. Only the stored value is checked, not
the full resolution pipeline: resolving every row through several stages here
would give up the streaming this is built for. A `noindex` applied only
through a model-wide template, never entered on a specific record, is not
caught this way — if a whole model should never appear in the sitemap, do not
register it as a source.

**Video sitemaps** attach to whatever a model already yields — implement
`HasSitemapVideo` and the entry rides along on that record's own `<url>` block,
since a video belongs on the page that hosts it, not in a separate feed:

```php
public function seoSitemapVideos(): array
{
    return [new SitemapVideo(
        thumbnailLoc: $this->thumbnail_url,
        title: $this->name,
        description: $this->excerpt,
        contentLoc: $this->video_url,
        durationSeconds: $this->duration,
    )];
}
```

**News sitemaps** are a separate, stricter feed most projects never need —
Google News rejects an article older than 48 hours outright, so a `news` block
switches a model source into one that only ever lists what was just published:

```php
'sitemap' => ['sources' => [
    ['model' => Article::class, 'name' => 'tin-tuc', 'news' => [
        'publication_name' => 'Báo Của Tôi',
        'publication_language' => 'vi',
        'date_column' => 'published_at',
        // 'max_age_hours' => 48,
    ]],
]],
```

Because the window is narrow, this is the one sitemap source that resolves
every record through the full pipeline rather than only checking stored
values — a busy news site still only has a handful of articles from the last
two days, not the millions a general sitemap streams through.

### Search console verification, AI crawlers, and IndexNow

```php
'verification' => [
    'google' => env('SEO_VERIFY_GOOGLE'),
    'bing' => env('SEO_VERIFY_BING'),
    // yandex, pinterest, facebook — same idea
],
```

Paste the code each console gives you and it is emitted as a `<meta>` tag —
`google-site-verification`, `msvalidate.1`, and so on — in HTML output, the
`meta` array Nuxt/Vue get, and Next's own `verification.google` /
`verification.other` fields. Unset, and nothing is emitted for that console.

```php
'robots' => ['block_ai_crawlers' => true],
```

A separate decision from indexing: this disallows GPTBot, ClaudeBot,
Google-Extended and the rest of a curated list in `robots.txt`, without
touching whether Googlebot or Bingbot can still index the site — "can this be
searched" and "can this be used to train a model" are not the same question,
and a project should not have to hand-list every bot user-agent to answer
only one of them.

```bash
php artisan seo:indexnow /bai-viet-moi /bai-viet-khac
```

Bing, Yandex and Seznam pick up a changed URL almost immediately through
IndexNow instead of waiting for their next crawl — Google does not
participate, a submitted sitemap is still the only signal it reads. Off by
default (`seo.indexnow.enabled`), since installing a package must never start
an outbound request on its own; the key doubles as the file this package
serves at `/{key}.txt` for IndexNow to confirm the submission came from
whoever owns the domain, so generate one once and keep it in `.env` rather
than regenerating it. Nothing in this package calls the command or the
underlying `IndexNowSubmitter` automatically — listen for `SeoMetaSaved`
(fired after every `Seo::save()`) and submit from there if a project wants
that; auto-submitting on every save would mean a blocking outbound request on
every panel edit, for every project, whether or not IndexNow is relevant to
it. Every call is logged to `seo_indexnow_log` — one row per API call, not
per URL — so "did this submission actually go through" has an answer besides
the console output scrolling past. `seo.indexnow.log = false` turns that off.

### Audit history

A live analysis (the panel, `/analyze`, `Seo::analyzeModel()`) answers "how
is this page doing right now." None of that is kept anywhere — `seo_meta`
holds the latest stored values, not a trend line — so there is no way to
answer "is the site's SEO getting better or worse" without something that
keeps score over time:

```bash
php artisan seo:audit "App\Models\Post" --content=body
```

Scores every record the same way a live analysis would, and writes one
`seo_audit_batches` row for the run (record count, average/min/max score)
plus one `seo_audits` row per record (its score and which checks failed).
Run it again next week and there are two batches to compare. Not scheduled
by this package itself: scoring readability and keyword usage needs the
record's actual body content, and only the application knows which attribute
holds that — `--content` names it, the same way `seo.models.*.route` exists
for URLs rather than this package guessing a column name. Schedule it
yourself with Laravel's own scheduler if a project wants it to run nightly.

### Internal links

```bash
php artisan seo:internal-links "App\Models\Post" --content=body
```

Crawls one model's own content for internal links — reusing the same
`ContentExtractor` the analyser already uses, not a second HTML parser — and
reports which of its own pages nothing in that set links to. A blog with a
hundred posts that never link to each other is exactly what this catches.
Matching is done by URL *path*, deliberately: a href made absolute against
`app.url` and a model's own `seoUrl()` override can legitimately disagree on
scheme or host (a CDN domain, a reverse proxy, `app.url` simply not matching
what a model returns), and comparing the full URL would report a page as
orphaned over that mismatch alone. Every crawl of one record replaces its
rows in `seo_internal_links` outright, so removing a link from the content
removes it here too on the next run — nothing accumulates.

Scoped to one model at a time rather than a true site-wide graph across every
exposed type, the same boundary `seo:duplicates` and `seo:hreflang` draw:
useful without needing every model in the application registered here the
way `seo.api.models` is for the REST API.

### Search Console stats

```bash
php artisan seo:search-console:sync --days=30
```

Pulls clicks, impressions, CTR and average position per page from the Search
Console API into `seo_search_console_stats` — free, but only for pages
Google has already indexed and shown in a real result, which is what
actually separates this from keyword rank tracking. A rank tracker answers
"where do I rank for a keyword I chose," which Google has no free API for at
all; this only ever answers "how are the pages I already have doing."

Off by default, and needs a one-time manual setup this package cannot do on
a project's behalf: a Google Cloud project with the Search Console API
enabled, an OAuth client (Desktop app type), and a refresh token obtained
once by sending that client through Google's consent screen yourself. This
package never runs that consent flow — only the resulting refresh token,
which does not expire the way an access token does, is ever used here:

```php
'search_console' => [
    'enabled' => true,
    'client_id' => env('SEO_SEARCH_CONSOLE_CLIENT_ID'),
    'client_secret' => env('SEO_SEARCH_CONSOLE_CLIENT_SECRET'),
    'refresh_token' => env('SEO_SEARCH_CONSOLE_REFRESH_TOKEN'),
    'site_url' => env('SEO_SEARCH_CONSOLE_SITE_URL'), // e.g. 'https://trangcuatoi.vn/'
],
```

`--days` sets the lookback window, ending 3 days back rather than today —
Search Console's own numbers for a day keep shifting for about 48 hours
after it happens, and syncing too early just means syncing the same day
again once the real numbers land. Re-running the sync updates a day's row
rather than duplicating it.

### Redirects and the 404 monitor

```php
app(RedirectRepository::class)->create('/cu', '/moi');
app(RedirectRepository::class)->create('/blog', '/tin-tuc', type: RedirectMatchType::Prefix);
```

Rules are consulted only once a request has already 404ed, so live routes are
never shadowed and the common path costs nothing. Three checks run at write
time and cannot be switched off: off-site targets, catastrophic regex patterns,
and redirect loops.

The same off-site check guards a stored **canonical URL** through the API and
the panel — a canonical pointed at another domain tells search engines this
page's real home is elsewhere and can pull it out of the index entirely, the
same class of mistake as an open redirect, just quieter. Add a host to
`seo.redirects.allowed_hosts` to permit it deliberately; that list is shared
between redirect targets and canonical URLs, since both are the same trust
boundary.

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

### Duplicate titles and descriptions

Two live checks, one cheap and one thorough — the same split as the sitemap's
noindex filter, for the same reason.

Saving through the API or the panel checks the *stored* title/description
against every other record's stored value and returns a warning — cheap
enough for a request a save is waiting on:

```json
{ "resolved": {...}, "warnings": { "duplicate_title": [{ "type": "post", "id": "12" }] } }
```

```bash
php artisan seo:duplicates App\\Models\\Post --field=both
```

resolves every record through the full fallback chain instead, catching what
the live check cannot: two untitled posts that both inherit the same
per-model template still show Google the identical title in two different
search results, and nothing was ever *stored* to compare. It does not scale
to millions of rows the way `seo:sitemap` does — it is an occasional audit,
not a request-path check.

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

### Dynamic settings

Everything above is `config/seo.php` — a file, changed by editing it and
deploying. `seo.settings.enabled = true` makes a specific, allowlisted subset
of it changeable at runtime instead, over the same `/api/seo/v1` Gate as
everything else, for a project that wants a settings *page* rather than a
settings *file*:

```php
'settings' => ['enabled' => true],
```

```ts
await fetch(`${API}/api/seo/v1/dynamic-settings`)  // GET — every allowlisted key, its value, and whether it's overridden
await fetch(`${API}/api/seo/v1/dynamic-settings`, {
  method: 'PUT',
  body: JSON.stringify({ settings: { 'verification.google': 'abc123' } }),
})
await fetch(`${API}/api/seo/v1/dynamic-settings/verification.google`, { method: 'DELETE' })  // revert to the config file's own value
```

This package ships no UI for it — the point of the API is that whichever
front end reads `GET` to render a form and calls `PUT` to save it, without
either needing to know the other exists.

Nothing that already reads `config('seo.*')` — `HtmlFormatter`,
`RobotsTxt`, `GlobalDefaultStage`, all of it — changed to support this.
`SettingsRepository::applyToConfig()` runs once at boot, before any of them,
and pushes every stored override straight into Laravel's own config
repository; every existing consumer picks it up simply because it was
already reading `config()`. Only the dot-notated keys listed in
`seo.settings.keys` can ever be written this way — the same reasoning
behind `seo.api.models` allowlisting which model types the API can touch,
rather than accepting any key a caller names.

`seo.settings.secret_keys` — a subset of the above, currently
`search_console.client_secret` and `search_console.refresh_token` — are
still writable through the same `PUT`, but `GET` never echoes their value
back, not even the one already sitting in `config/seo.php`:

```json
{ "search_console.refresh_token": { "is_set": true, "overridden": true, "secret": true } }
```

An OAuth client secret or refresh token has no legitimate reason to be
readable again once it is set, and no safe partial-reveal convention the way
a card number's last four digits does — unlike `indexnow.key`, published on
purpose at `/{key}.txt`, or `search_console.client_id`, routinely visible in
a browser's own OAuth redirect URL, both of which report their real `value`
like anything else here.

### AI assistance

Off by default, because installing a package must never start billing anyone.

```php
Seo::ai()->suggestMeta($html, keyword: 'tối ưu SEO', locale: 'vi');
Seo::ai()->suggestKeywords($html, 'vi');

// Anything else — Ollama, a local model, an internal API
Seo::ai()->extend('my-llm', fn () => new MyDriver());
```

Claude, OpenAI and Gemini are reached over plain REST through Laravel's HTTP
client; no vendor SDK is required or even suggested. Each driver asks its
provider for schema-constrained output — tool use, `json_schema`,
`responseSchema` — and prose where an object was expected is an error, never
something to parse.

Prompts are translated, not just their output: an English instruction asking
for a Vietnamese description reliably produces stilted Vietnamese. Results are
cached by content hash, every call is logged with its tokens, and a daily token
budget caps what a runaway loop can spend.

### The npm client

[`@duxbo/seo-core`](js/packages/core/) holds the types, the API client, and the
state handling every front end needs — dirty tracking, debounced analysis,
contract-version checking. It renders nothing.

```ts
const store = createMetaStore(seo, { type: 'post', id: 42 })
await store.load()
store.set('title', 'Tiêu đề mới')
store.isDirty     // true
store.analyze(html)   // debounced; a stale response cannot overwrite a newer one
```

That split is the point: the hard part of an SEO panel is knowing what changed
and when to re-score, not the markup. Writing it once means a React or Vue
adapter is a few hundred lines of rendering rather than a reimplementation.

### UI: React, Vue, or Blade — pick one, or none

Every UI here talks to the same backend and shares the same `viewSeoPanel`
Gate. None is required; `seoTags()` alone is a complete, working integration
with no admin UI at all.

**[`@duxbo/seo-react`](js/packages/react/)** and **[`@duxbo/seo-vue`](js/packages/vue/)**
— hooks/composables plus a Tailwind-styled admin shell, built on
`@duxbo/seo-core`, for a project with a front-end build step. `SeoPanel`
edits one record; five more components cover the rest, each fetching through
the same `SeoClient` and none of them routing on its own:

```tsx
<SeoPanel client={client} target={{ type: 'post', id: post.id }} content={post.body} />
<SeoDashboard client={client} onSelectType={(type) => router.push(`/admin/seo/content?type=${type}`)} />
<SeoContentList client={client} type="post" onEdit={(type, id) => router.push(`/admin/seo/${type}/${id}`)} />
<SeoRedirects client={client} />
<SeoNotFoundMonitor client={client} />
<SeoSettings client={client} />
```

All six need `seo.api.enabled = true` — they talk to `/api/seo/v1`, not the
Blade panel's session routes — and this package's build output added to
Tailwind's `content` globs, or the classes are purged and everything renders
unstyled.

**Blade**, for a project with none of that. `php artisan vendor:publish
--tag=seo-views` to customise it, or use it as shipped:

```php
'panel' => ['enabled' => true],
```

`/seo/panel` is a small admin shell, not just the one editor page:

| Route | Purpose |
|---|---|
| `/seo/panel` | Dashboard — records with SEO data, missing meta by type, active redirects, 404 count |
| `/seo/panel/content?type=post` | Paginated list of one model type with its resolved title |
| `/seo/panel/{type}/{id}` | The single-record editor — title/description/keyword, live score |
| `/seo/panel/redirects` | Create, toggle, and delete redirects |
| `/seo/panel/not-found` | 404 log — prune old entries, or turn a hit straight into a redirect |
| `/seo/panel/settings` | Read-only view of the master switch, allowlists, and enabled surfaces |

Every page is plain `fetch()` and scoped `seo-`-prefixed CSS — no build step, no
Tailwind requirement, no JS framework. It talks to its own routes under `web`
middleware (session and CSRF), not the token-based REST API: a same-origin
admin page already has both, and routing through bearer tokens would mean
standing up Sanctum just for this. It shares the API's `seo.api.models`
allowlist regardless of which surface is used.

| Milestone | Scope | State |
|---|---|---|
| M1 | Contracts, data objects, Compat | done |
| M2 | Storage, `HasSeo` trait, resolution pipeline, HTML output | done |
| M3 | schema.org `@graph` | done |
| M4 | Sitemap, robots.txt | done |
| M5 | Redirects, 404 monitor | done |
| M6 | HTTP API, headless formatters | done |
| M7 | Content analysis | done |
| M8 | AI drivers | done |
| M9 | `@duxbo/seo-core` npm package | done |
| M10 | Docs, full CI matrix, 1.0 | done |

## Requirements

| | |
|---|---|
| PHP | 8.2 – 8.4 (tested) |
| Laravel | 12, 13 (tested) |
| Extensions | `dom`, `json`, `libxml` (`intl` optional, with a fallback) |

### Octane and long-running queue workers

Supported without depending on either: a handful of this package's
singletons cache something in an instance property rather than only in
Laravel's own `Cache` store — `CachedRedirectMatcher`'s loaded rule set,
`AiManager`'s built drivers, dynamic settings' applied config. Under
ordinary PHP-FPM that never matters, since the whole container is rebuilt
fresh every request. Under Octane (Swoole, RoadRunner, FrankenPHP) or an
ordinary `php artisan queue:work`, the same singleton instance persists
across many requests or jobs, and an instance property does not know a new
one has started on its own.

Both runtimes fire their own event before each unit of work —
`Laravel\Octane\Events\RequestReceived` and `Illuminate\Queue\Events\JobProcessing`
— and this package listens for both by the event's class name as a plain
string, not an imported class. Neither `laravel/octane` nor `illuminate/queue`
is a dependency of this package; the listeners simply never fire, and cost
nothing, on a runtime where the matching event does not exist. A class that
needs this reset implements `Contracts\ResetsBetweenRequests` — a custom
`RedirectMatcher` or `AiDriver` a project supplies has no reason to unless it
caches the same way.

### About Laravel 9, 10 and 11

The Composer constraint still admits them, and the compatibility layer still
carries their code paths — but they are **not tested, and in practice not
installable**.

All three are past their security end-of-life (February 2024, February 2025 and
March 2026), and every published release now carries unpatched advisories.
Composer treats that as a hard resolver failure, not a warning:

```
- orchestra/testbench v7.57.0 requires laravel/framework ^9.52.21
  -> found laravel/framework[v9.52.21, v9.52.22] but these were not loaded,
     because they are affected by security advisories
```

A CI row for those versions would prove only that Composer's security policy can
be switched off. If you are locked to one of them and have already made that
decision for your own project, the package will very likely work — but nothing
here verifies it.

PHP 8.1 remains the language floor because the design leans on enums, readonly
properties, `never`, first-class callables and pure intersection types. In
practice Laravel 12 requires 8.2, so 8.2 is the effective minimum.

## Testing

The suite runs against every supported combination inside throwaway Docker
containers, so nothing has to be installed on the machine:

```bash
docker/matrix.sh            # whole matrix
docker/matrix.sh 13         # one Laravel major
docker/matrix.sh --clean    # remove the built images
```

The source is mounted read-only and copied inside the container, so the host's
`vendor/` and `composer.lock` are never touched.

```
  PASS  Laravel 12 · PHP 8.2    OK (147 tests, 272 assertions)
  PASS  Laravel 12 · PHP 8.3    OK (147 tests, 272 assertions)
  PASS  Laravel 12 · PHP 8.4    OK (147 tests, 272 assertions)
  PASS  Laravel 13 · PHP 8.3    OK (147 tests, 272 assertions)
  PASS  Laravel 13 · PHP 8.4    OK (147 tests, 272 assertions)
```

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
