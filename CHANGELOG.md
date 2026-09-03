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

### Notes on supported versions

The Composer constraint admits Laravel 9 through 13, but only 12 and 13 are
tested — and in practice only those two can be installed at all. Laravel 9, 10
and 11 are past security end-of-life and every published release carries
unpatched advisories, which Composer treats as a hard resolver failure. See the
README for the details.
