# Changelog

Notable changes, newest first. This project follows [semantic versioning](https://semver.org),
with one addition: **everything in `src/Contracts` is the public API**, and it
only changes in a major release. The rest of `src/` is free to be refactored.

## Unreleased — 0.9.0

Feature-complete and fully tested, but not yet 1.0. Nothing here has run in a
production site, and that is the only thing that turns a well-built package
into a hardened one — the edge cases that matter are the ones real projects
find. `Contracts/` is frozen at 1.0, so it stays open until then.

### Added

- **Metadata** — polymorphic storage behind a swappable repository, a
  seven-stage resolution pipeline configured rather than hard-coded, 14 tokens,
  and per-locale records with automatic hreflang.
- **Structured data** — one flat `@graph` whose nodes reference each other by
  `@id`, with dangling references pruned. Models describe themselves by
  returning a plain array, so any schema.org type works.
- **Sitemaps** — `XMLWriter` streaming over `lazyById()`, split at the
  protocol's 50,000-URL limit, with opt-in sources.
- **Redirects and 404 monitoring** — three write-time safety checks that cannot
  be disabled, and a 404 table that cannot outgrow its configured size.
- **Content analysis** — 17 checks, each declaring which locales it
  understands. Vietnamese gets its own readability and passive-voice measures
  instead of English formulas that would produce confident nonsense.
- **Headless output** — formatters emitting the exact shape Next.js, Nuxt and
  Vue expect, plus a REST API that is disabled by default behind a Gate that
  denies everyone until the application defines it.
- **AI assistance** — Claude, OpenAI and Gemini over plain REST, off by
  default, with schema-constrained output, translated prompts, response caching
  and a daily token budget.
- **`@duxbo/seo-core`** — the npm client: types, API client and the state
  handling every front end needs, with no rendering and no dependencies.
- **UI, in three flavours, none required** — `@duxbo/seo-react` and
  `@duxbo/seo-vue`, both a thin hook/composable over `@duxbo/seo-core` plus a
  Tailwind-styled `<SeoPanel>`; and a Blade admin shell at `/seo/panel` for a
  project with no front-end build step at all — plain `fetch()`, scoped CSS,
  no Tailwind requirement. The Blade panel talks to its own routes under `web`
  middleware (session + CSRF) rather than the token-based REST API, since a
  same-origin admin page already has both.

### Added — Blade admin shell

The single-record editor at `/seo/panel/{type}/{id}` had no menu around it —
nothing rank-math-like to land on first, no way to see redirects or 404s
without the database console. Built as thin controllers over repositories
that already existed; no backend logic duplicated:
- **Dashboard** (`/seo/panel`) — records with SEO data vs. total per type,
  active redirect count, 404 count, configured sitemap sources, and a warning
  banner when `seo.enabled` is off.
- **Content list** (`/seo/panel/content?type=post`) — every record of one
  type with its resolved title, paginated with a hand-rolled prev/next pager
  rather than Laravel's default view, which pulls in Tailwind.
- **Redirects** (`/seo/panel/redirects`) — create, toggle, and delete, reusing
  `RedirectRepository`; an `UnsafeRedirect` from the existing open-redirect
  guard now surfaces as a form validation error instead of a 500.
  `RedirectRepository` gained `setActive()` and `deleteById()` for this, both
  flushing the route matcher's cache like every other write already does.
- **404 monitor** (`/seo/panel/not-found`) — prune entries older than N days,
  or turn one hit straight into a redirect and remove it from the log in the
  same action.
- **Settings** (`/seo/panel/settings`) — read-only status of the master
  switch, allowlists, and which optional surfaces are enabled; nothing here
  writes.

All five share one Gate (`viewSeoPanel`) and one layout with a nav badge for
the current 404 count, fed by a view composer registered once in the service
provider. The fixed-segment routes (`redirects`, `not-found`, `content`,
`settings`) sit alongside the pre-existing `{type}/{id}` catch-all; a test
hits every one of them for real rather than trusting that the segment counts
can't collide.

### Added — internal link graph, Search Console sync

The other two real gaps from the same table-by-table comparison, both
genuinely free (no paid third-party service, unlike keyword rank tracking):

- **Internal links** — `php artisan seo:internal-links {model} --content={attribute}`
  crawls one model's own content for internal links, reusing the same
  `ContentExtractor` the analyser already runs rather than a second HTML
  parser, and reports which of its own pages nothing in that set links to.
  Matching is by URL path rather than full URL on purpose: a href made
  absolute against `app.url` and a model's own `seoUrl()` override can
  legitimately disagree on scheme or host, and comparing full URLs would
  report a page as orphaned over that mismatch alone rather than a real
  missing link. Every crawl replaces one record's rows in
  `seo_internal_links` outright.
- **Search Console sync** — `php artisan seo:search-console:sync` pulls
  clicks, impressions, CTR and position per page into
  `seo_search_console_stats`. Free, but distinct from keyword rank
  tracking: it only ever reports on pages Google has already indexed and
  shown in a real result, never an arbitrary keyword chosen in advance,
  which is what a paid SERP-tracking service is for instead. Needs a
  one-time manual OAuth setup outside the package (a Google Cloud project,
  an OAuth client, one consent-screen visit for a refresh token) — this
  package never runs that consent flow itself, only the resulting refresh
  token afterward.

### Added — schema escape hatches, IndexNow submission log, audit history

Compared this package's tables against a competing SEO module's schema
(seo_404_logs, seo_audits, seo_instant_indexing, seo_internal_links,
seo_keyword_rankings, seo_search_console_stats, …) to see what a genuinely
different kind of table represented, versus what was already covered under a
different name:

- **Schema escape hatches** — `OrganizationProvider` and `WebSiteProvider`
  used to build their node from a fixed field whitelist; anything else in
  `seo.schema.organization.*` / `seo.schema.website.*` is now merged straight
  through. The same inconsistency `Types::product()`'s own `$extra` parameter
  already avoided elsewhere in this same file — a fixed whitelist is always
  one field short of whatever the next project asks for, and there was no way
  to add `geo`, `foundingDate`, or `areaServed` without forking the class.
- **IndexNow submission log** — `seo_indexnow_log`, one row per API call
  (not per URL), recording whether it succeeded and the response status —
  answers "did this actually go through" without scrolling back through
  console output. `seo.indexnow.log = false` turns it off.
- **Audit history** — `php artisan seo:audit {model} --content={attribute}`
  scores every record the same way a live analysis does and keeps the
  result: one `seo_audit_batches` row per run (count, average/min/max score),
  one `seo_audits` row per record (its score, which checks failed). A live
  analysis and `seo_meta` both only ever answer "right now" — this is what
  answers "is the site's SEO trending up or down," which needed a table that
  did not otherwise exist. Not scheduled automatically: scoring content needs
  the record's actual body, and only the application knows which attribute
  holds it, the same reasoning behind `seo.models.*.route` for URLs.

Keyword rank tracking, from the same comparison, is deliberately not here:
Google offers no free rank-position API, so it needs a paid third-party SERP
service the application must choose and pay for itself — the same
bring-your-own-key shape the AI drivers already use, not something this
package can turn on by default the way the two additions above are.

### Added — search console verification, AI crawler control, IndexNow, hreflang collision audit

Four gaps from a third audit, this time asking not "is the core stable" but
"is it enough to actually rank well on Google and elsewhere":

- **Search console verification** — `seo.verification.{google,bing,yandex,pinterest,facebook}`,
  emitted as the matching `<meta>` tag (`google-site-verification`,
  `msvalidate.1`, …) by `HtmlFormatter` and `HeadFormatter`, and mapped to
  Next's native `verification.google` / `verification.yandex` /
  `verification.other` fields by `NextMetadataFormatter`. Read once by a new
  `Support\SiteVerification`, since the value is site-wide rather than
  per-record and does not belong in the resolution pipeline everything else
  goes through.
- **AI crawler blocking in robots.txt** — `seo.robots.block_ai_crawlers` (off
  by default) disallows a curated list of AI-training user-agents (GPTBot,
  ClaudeBot, Google-Extended, CCBot, …) in a separate `User-agent` block per
  bot, deliberately independent of the existing `groups` config: whether a
  site can be searched and whether it can be used to train a model are two
  different decisions, and a project wanting one without the other should
  not have to hand-list every bot itself.
- **IndexNow** — `IndexNow\IndexNowSubmitter` posts to the shared IndexNow
  endpoint so Bing, Yandex and Seznam pick up a changed URL immediately
  instead of waiting for their next crawl (Google does not participate;
  a submitted sitemap is still the only signal it reads). Off by default,
  and calling it while off is a silent no-op — the same promise the AI
  manager's `NullDriver` makes — but `enabled = true` with no key fails
  loudly, since a developer who explicitly turned it on almost certainly
  meant to set one too. The key doubles as the filename (`{key}.txt`) this
  package now serves at the site root, registered as a literal route rather
  than a wildcard so it cannot shadow anything else. `php artisan
  seo:indexnow {urls*}` for manual or scripted submission. A new
  `SeoMetaSaved` event fires after every `Seo::save()` — the extension point
  for a project that wants IndexNow, or anything else, triggered
  automatically; this package does not wire that up itself, since a blocking
  outbound request on every panel save is not something every project
  installing this package wants.
- **Hreflang collision audit** — `php artisan seo:hreflang {model}`. This
  package's hreflang alternates all come from one record's own URL asked for
  in different locales, so the classic "hreflang isn't reciprocal" bug (page
  A points to B, B never points back) cannot happen by construction here.
  What can still happen: a misconfigured `locale_parameter` mapping or a
  custom `alternate_url` resolver that ignores its `$locale` argument,
  producing the *same* URL for two different `hreflang` values — which gets
  the whole cluster discarded by Google just as surely. The command resolves
  every record's alternates the same way the formatters do and flags any
  that collide.

### Added — the same admin shell for React and Vue

The Blade shell above had no equivalent for a project with a front-end build
step — `@duxbo/seo-react` and `@duxbo/seo-vue` only had `SeoPanel`, the
single-record editor. Five new components close that gap, one per Blade
page: `SeoDashboard`, `SeoContentList`, `SeoRedirects`, `SeoNotFoundMonitor`,
`SeoSettings`. None of them route — `onSelectType`/`onEdit` (React props) and
`selectType`/`edit` (Vue emits) hand navigation back to the host app rather
than assuming a router exists.

They talk to `/api/seo/v1`, not the Blade panel's session routes, so five
JSON endpoints were added behind the same `viewSeoPanel` Gate as the rest of
the REST API: `GET dashboard`, `GET content`, `GET settings`, `GET|POST
redirects` + `PATCH redirects/{id}/toggle` + `DELETE redirects/{id}`, and
`POST not-found/prune` + `POST not-found/{id}/redirect` alongside the
existing `not-found` index/destroy. Every one of them is the same repository
call the Blade controllers already make — `RedirectRepository`,
`NotFoundLogger`, `MetadataRepository`, `SitemapGenerator` — reached through
a JSON twin of each Blade panel controller rather than new business logic.

Two additions to `@duxbo/seo-core` came out of building these: the `SeoClient`
interface gained the matching methods (`dashboard()`, `content()`,
`settings()`, `redirects()`, `createRedirect()`, `toggleRedirect()`,
`deleteRedirect()`, `pruneNotFound()`, `convertNotFoundToRedirect()`), and
`SeoApiError` gained `fieldErrors()` / `fieldError(field)` — Laravel's 422
response carries the specific validation reason under `errors.field`, not in
the generic top-level `message`, and an unsafe-redirect rejection is unreadable
without unwrapping that envelope.

`NotFoundEntry.path` (and `referrer`/`user_agent`) is already HTML-escaped by
the REST API, established when the API was first built — `SeoNotFoundMonitor`
renders it with `dangerouslySetInnerHTML` / `v-html` for that reason, not
despite it: plain text interpolation would double-escape it into literal
`&lt;` text instead of the path Google actually requested.

### Added — three roadmap items from the second audit's "not urgent" list

Confirmed via search rather than assumed: Google's current recommendation is
`max-image-preview:large` for the most traffic from image results and
Discover (up to 333% more clicks) — set as `seo.defaults.robots`'s default.
A stored per-page value still overrides it like any other default; `null`
opts the whole site out. Two existing tests that asserted "no robots line at
all" for a plain indexable page were updated to reflect the new default
rather than left passing for the wrong reason.

**Duplicate title/description detection**, in two parts with different cost
budgets — the same split the sitemap's noindex filter already uses:
- A live check at save time (`MetadataRepository::duplicateTitles()` /
  `duplicateDescriptions()`, new methods since the package is still pre-1.0
  and `Contracts/` stays open until it isn't) compares only *stored* values
  against other records — cheap enough for a request a save is waiting on.
  Both the REST API and the panel now return a `warnings` key alongside
  `resolved` after a save, shared through one `WarnsAboutDuplicates` trait
  rather than duplicated across both controllers.
- `php artisan seo:duplicates {model} --field=title|description|both`
  resolves every record through the full fallback chain instead, catching
  what the live check structurally cannot: two untitled posts that both
  inherit the same per-model template still show Google an identical title
  in two different search results, and there was never a stored value to
  compare in the first place. Explicitly not built for the row counts
  `seo:sitemap` handles — an occasional audit, not a request-path check.

**Video and news sitemap support**, added as extensions of the existing
sitemap rather than a parallel subsystem:
- `HasSitemapVideo` lets a model attach `<video:video>` entries to whatever
  `ModelSource` already yields for it — a video belongs on the page that
  hosts it, not in a feed of its own.
- A `'news'` block on a model source definition builds a `NewsSitemapSource`
  instead of a plain one: Google News rejects an article older than 48
  hours outright, so this is a genuinely different, stricter feed rather
  than an option on the general one. Because the window is narrow, it is
  the one sitemap source allowed to resolve every record through the full
  pipeline rather than only checking stored values — a busy news site still
  only has a handful of articles from the last two days, not the millions a
  general sitemap has to stream through — and it excludes a stored-noindex
  article the same way `ModelSource` does.
- `SitemapWriter` gained the `video`/`news` XML namespaces and element
  writers alongside the `image`/`xhtml` ones it already had.

### Added / Fixed — second core audit

- **Canonical URLs pointed at another domain were accepted with zero
  validation**, through both the REST API and the Blade panel. A canonical
  set to an outside URL tells search engines this page's real home is
  elsewhere and can pull it out of the index — the same class of mistake as
  an open redirect, just quieter, and this package already treats an
  unrestricted redirect target as a real vulnerability rather than a
  preference. Fixed by extracting the host-allowlist check `RedirectGuard`
  already had into a shared `SameOriginUrls`, now also enforced on the
  `canonical` field in both write endpoints. `seo.redirects.allowed_hosts`
  is the one list both surfaces read.
- **`/analyze` had no rate limit**, unlike the AI path which has a token
  budget. Content analysis parses HTML and runs every registered check per
  request; a buggy or malicious authenticated client could hammer it since
  both routes sit behind `viewSeoPanel` but nothing capped call volume.
  Added `seo.analysis.rate_limit` (default `30,1`), applied via Laravel's
  `throttle` middleware to both the API and panel `/analyze` routes.
- **Added `og:article:*` support** — `publishedTime`, `modifiedTime`,
  `author`, `section`, `tag`, emitted only under `og.type = 'article'` per
  the Open Graph spec. A content site's link previews on Facebook and
  LinkedIn were missing byline and publish-date decoration that every real
  article page benefits from. Wired through `OpenGraphData`,
  `SeoDataBuilder`, `SeoDataMapper` (so it survives a save/load round trip),
  and all three formatters that emit Open Graph — `HtmlFormatter`,
  `HeadFormatter`, and `NextMetadataFormatter` (nested under Next's own
  `authors`/`tags` shape).

Confirmed safe rather than assumed: `DomContentExtractor`'s `DOMDocument`
usage was checked against an actual XXE payload:

```php
$d->loadHTML('<?xml encoding="UTF-8"><div>' . $maliciousXml . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
```

does not resolve external entities on the PHP versions this package
supports — no change needed, but asserted rather than taken on faith.

### Added — demo-domain master switch

`SEO_ENABLED=false` forces `noindex,nofollow` on every page — unconditionally,
where `indexable_environments` is a defeatable default — disallows everything
in `robots.txt`, and empties the sitemap. The safety net for a client-preview
domain that must never reach the index, where the alternative is forgetting
one flag and having Google index throwaway content under a real client's
domain. Meta tags, Open Graph and canonical links keep rendering, so a link
shared in Slack still previews correctly.

### Fixed — two real SEO defects, found by evaluating the package against what
technical SEO actually requires rather than against its own tests

- **hreflang pointed at translations that do not exist.** Every formatter and
  the sitemap assumed a record existed in every `seo.locales.supported`
  locale and emitted an alternate for each one regardless of whether that
  specific record had been translated — so a Vietnamese-only post got a
  `hreflang="en"` link pointing at a URL that 404s. Google does not merely
  ignore that one broken link; it can discard the entire hreflang cluster
  over it. Fixed with a new `AlternateLocaleResolver`, one place instead of
  four independently-guessing ones: a model implementing the new
  `HasAlternateLocales` contract is trusted outright, and without it only
  locales with their own stored `seo_meta` row count as evidence. The
  current locale being rendered is free (a formatter is obviously rendering
  in it); the sitemap has no such freebie and needs at least two locales
  with real evidence before emitting any alternate at all.
- **The sitemap could list a page its own robots meta marks noindex.**
  `ModelSource` handed every record straight to `SitemapUrl` without ever
  checking its metadata — an editor marking one page noindex through the
  panel did not stop it appearing in the sitemap, which is precisely the
  contradiction Search Console flags as "Submitted URL marked noindex."
  Fixed by batching a stored-metadata lookup per chunk (`findMany()` once
  per `lazyById()` chunk, not once per row) and skipping a record whose
  stored value marks it noindex — deliberately not resolving the full
  pipeline per record, which would have given up the streaming design this
  class exists for.

One real bug surfaced while fixing the above: `LazyCollection::chunk()`
returns chunks that are themselves `LazyCollection`, not the eager
`Illuminate\Support\Collection` `findMany()` requires — caught immediately by
the test suite as a `TypeError`, fixed with `->collect()` per chunk.

### Fixed — core audit

A pass over the whole `src/` tree looking for what documentation claims and
what code actually does had drifted apart, and for surfaces with no test
coverage.

- `composer.json` was missing `ext-mbstring` and `ext-xmlwriter` from
  `require`, despite `mb_*` functions appearing in nine files and `XMLWriter`
  driving the whole sitemap writer. Both extensions ship enabled by default in
  virtually every PHP build, which is exactly why the gap went unnoticed —
  but an extension the code calls belongs in the manifest regardless of how
  likely it is to be present. The CI workflow's `extensions:` lists had the
  same gap and are corrected alongside it.
- `seo:sitemap` and `seo:prune-404` had zero test coverage — every other
  public entry point (routes, the facade, the REST API) was exercised
  somewhere, and the two Artisan commands were not. Added tests covering both
  the success and failure paths of each.
- `HasBreadcrumbs` existed, worked, and was tested (§3), but was never
  mentioned in the README's structured-data section — documented now.

### Notes on supported versions

The Composer constraint admits Laravel 9 through 13, but only 12 and 13 are
tested — and in practice only those two can be installed at all. Laravel 9, 10
and 11 are past security end-of-life and every published release carries
unpatched advisories, which Composer treats as a hard resolver failure. See the
README for the details.
