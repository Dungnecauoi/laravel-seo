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
