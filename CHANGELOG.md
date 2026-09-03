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
  Tailwind-styled `<SeoPanel>`; and a Blade panel at `/seo/panel/{type}/{id}`
  for a project with no front-end build step at all — plain `fetch()`, scoped
  CSS, no Tailwind requirement. The Blade panel talks to its own routes under
  `web` middleware (session + CSRF) rather than the token-based REST API, since
  a same-origin admin page already has both.

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
