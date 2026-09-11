# Changelog

Notable changes, newest first. This project follows [semantic versioning](https://semver.org),
with one addition: **everything in `src/Contracts` is the public API**, and it
only changes in a major release. The rest of `src/` is free to be refactored.

## Unreleased

## 0.12.2 — 2026-09-11

Findings from a full security/correctness re-audit of everything added
since 0.9.0, spread across 3 parallel reviews. Ranked by severity.

### Fixed — SSRF: an obfuscated IP literal bypassed `PublicUrlGuard`

`http://0177.0.0.1/` (and equivalent decimal-integer/hex encodings) was
not recognised by `filter_var(FILTER_VALIDATE_IP)`, so it fell through to
DNS resolution as if it were a hostname — resolved to nothing, and looked
"safe" purely because the guard had no opinion on a string that isn't a
real domain. The real HTTP client's underlying cURL/libc resolver parses
the identical string with C-style numeral semantics and connects to
`127.0.0.1` regardless — a confirmed, working SSRF against this server's
own loopback via any model content `seo:broken-links` crawls.
`PublicUrlGuard::looksLikeIpLiteral()` now refuses any host built only
from digits/dots/an "0x" prefix outright (no real hostname is shaped that
way, so this never blocks a genuine one) rather than trying to enumerate
every numeral encoding a resolver might accept.

### Fixed — SSRF: DNS-rebinding gap in `PublicUrlGuard`/`BrokenLinkChecker`

The guard's own DNS lookup and the HTTP client's independent lookup
moments later were two separate resolutions of the same hostname, with no
IP pinning between them — a very-short-TTL DNS record under attacker
control could answer publicly for the check and privately for the real
connection. `PublicUrlGuard::resolve()` now returns the exact IP it
approved, and `BrokenLinkChecker` pins the real request to that literal
address via `CURLOPT_RESOLVE` rather than letting the hostname resolve a
second time.

### Fixed — `registerMiddleware()` could permanently disable 404 logging/redirects on one Octane worker

Same class of bug as the `runningInConsole()` commands() fix two releases
ago: whether `HandleNotFound` was ever pushed onto the middleware stack
was decided once, from whatever `seo.redirects.enabled`/`seo.not_found.enabled`
read at boot. A worker that booted with both flags off would never get
the middleware at all, and flipping either back on later through Dynamic
Settings (which only ever rewrites the live `Config` singleton, never
re-runs a provider's `boot()`) would silently do nothing until that
worker restarted. The middleware is now pushed unconditionally —
`HandleNotFound` already re-reads both flags itself on every request, so
there's nothing to gain by deciding this at boot instead.

### Fixed — `CatastrophicPattern` only caught nested-quantifier ReDoS, not alternation-based

`(a|aa)+$` (ambiguous alternation, a different vulnerable shape from
`(a+)+`) sailed straight through the old signature-matching regex and
demonstrably burned real CPU via `pcre.backtrack_limit` exhaustion on
every 404, reachable by any anonymous visitor. Rewritten to actually
*run* the pattern against a handful of short, deliberately non-matching
probe strings and check whether PCRE's own backtrack-limit safety net
had to step in, rather than recognising one specific dangerous construct
— generalises to shapes this class was never told about by name. Also
fixed in the same change: the probes now try both a bare and a
`/`-prefixed form, since every pattern this class guards matches against
a URL path and a `^/(a+)+$`-style pattern's own anchor was failing the
bare probe before backtracking ever began, wrongly looking safe.

### Fixed — `NotFoundLogger::log()`'s upsert was a TOCTOU race that surfaced as an uncaught 500

Two requests reporting the same never-before-seen path at the same
moment could both see the UPDATE match nothing and both attempt the
INSERT; the second threw an uncaught `UniqueConstraintViolationException`
straight through `HandleNotFound`/`NotFoundIngestController`, turning an
ordinary 404 into a 500 for whichever visitor (or SPA backend) lost the
race. The INSERT branch now catches that specific exception and finishes
as an update instead — verified with a test that deliberately triggers
the exact race via a `DB::listen()` hook, not just a sequential re-run.

### Fixed — batch size was unenforced on 3 AI tools making one real outbound request per URL

`seo.google_indexing.submit`, `seo.pagespeed.check`, and
`seo.search_console.inspect` looped every URL in `execute()` synchronously
with no cap — a single AI-authored URL list could run for an unreasonable
time or, for Search Console's own tightly-quota'd URL Inspection API
(~2,000 requests/day/site), exhaust a meaningful fraction of a whole
day's quota in one call. Each now refuses a batch over a fixed limit
(100/25/50 respectively) before making any request.

### Fixed — the 404 ingest endpoint's throttle ran before its own auth check

`throttle:120,1` was listed before `VerifyNotFoundIngestToken`, so an
unauthenticated caller (wrong token, no token) consumed the same per-IP
bucket as a legitimate one — anyone sharing that IP (or simply spamming
401s) could exhaust the real caller's own budget for the window. Token
check now runs first.

### Fixed — three lower-severity gaps found in the same audit

- `InternalLinksController`'s `?locale=`/`?type=` filters are now trimmed;
  a stray space previously matched nothing and silently reported every
  record as an orphan instead of an error. Both endpoints now also echo
  back the `locale` actually applied, so a caller can tell "nothing was
  crawled under this locale" apart from "these pages genuinely have no
  incoming links."
- `SampleRateSettingValidator` now explicitly rejects `NAN` (any
  comparison against `NAN` is false under IEEE-754, so the old bounds
  check silently let it through) — not reachable via the shipped
  JSON-decoded write path, but a real defensive gap for any future caller
  that isn't one.
- `seo_internal_links` gained a real unique constraint on
  `(source_type, source_id, locale, target_hash)`, so two *overlapping*
  crawls of the same source+locale now fail loudly instead of silently
  leaving duplicate rows that inflate `incomingLinks`/`outgoingLinks`
  counts. Standard SQL treats two `NULL` locales as distinct for
  uniqueness, so this protects a site that crawls with an explicit
  `--locale`, not a single-language site — a pre-existing limitation this
  column doesn't introduce.

### Changed — `seo:internal-links --locale` now refuses to run concurrently with itself on one worker

Mutating the shared application locale for the crawl's duration (needed
so a translatable model's `seoUrl()`/content reads the right language)
has an inherent gap under Octane: two *overlapping* `--locale` crawls on
the same worker could race to restore the app locale afterward,
potentially leaving it permanently wrong until the worker restarts. A
process-wide lock now refuses a second concurrent `--locale` crawl
outright rather than let that happen. What this does **not** protect
against — an unrelated request on the same worker observing the mutated
locale for the crawl's own duration — would need `Contracts\Seoable`
itself to accept a locale explicitly rather than reading
`app()->getLocale()` implicitly, a larger change than this release makes;
prefer a real `php artisan` process or a queued job over triggering this
synchronously mid-request on an Octane deployment with real concurrent
traffic.

## 0.12.1 — 2026-09-10

### Fixed

- **Every `seo:*` console command was unreachable via `Artisan::call()` from
  a web request** — meaning every `seo.console.*` AI tool
  (`ConsoleCommandTool::execute()`, called from the REST API, the panel,
  or MCP over HTTP) always failed with `CommandNotFoundException`,
  regardless of how the package was deployed. `SeoServiceProvider::boot()`
  called `$this->commands([...])` only inside `if ($this->app->runningInConsole())`.
  `ServiceProvider::commands()` doesn't register anything itself — it
  queues a deferred `Artisan::starting()` bootstrapper, run the first time
  `Artisan::call()` constructs its underlying console application. Gating
  the call to `commands()` behind `runningInConsole()` meant that
  bootstrapper was never queued during an ordinary web request (where
  `runningInConsole()` is false from the start), so the first
  `Artisan::call()` in that same request found no command registered at
  all — a straight `php artisan seo:...` from a terminal worked fine
  (that process really is running in console), masking the bug for anyone
  who only tested that way. `$this->commands([...])` now runs
  unconditionally in `boot()`; `publishes()`/`publishMigrations()` stay
  console-gated, since those only make sense under `vendor:publish`.
  PHPUnit itself runs under the CLI SAPI, so `runningInConsole()` was true
  throughout the entire existing test suite — the new
  `ConsoleCommandsAvailableOutsideConsoleTest` forces it false via
  `APP_RUNNING_IN_CONSOLE` to actually exercise the web-request case this
  bug lived in.

## 0.12.0 — 2026-09-10

### Added — a decoupled SPA front end can report its own 404s, browse the real internal-link graph, and read Search Console as a time series

Four gaps found auditing this package against a real production site whose
front end is a separately-deployed Next.js app, never proxied through
Laravel.

- **404 ingest endpoint.** `HandleNotFound` middleware only ever recorded a
  404 that flowed through *this* application's own router — a decoupled
  front end's real 404s never reached `seo_not_found` at all. New
  `POST {api.prefix}/not-found` accepts `{path, referrer?, userAgent?}`
  from that front end's own backend, authenticated by a new
  `seo.not_found.ingest_token` (bare shared secret, header
  `X-Seo-Ingest-Token`, compared with `hash_equals()` — there is no
  Laravel session for a server-to-server caller to hold). The route is
  only ever registered when the token is configured, and lives in
  `routes/seo.php` rather than the admin API group, so it works even with
  `seo.api.enabled` false. **Breaking**: `NotFoundLogger::log()` now takes
  a new `Data\NotFoundHit` value object instead of `Illuminate\Http\Request`,
  and `Events\NotFoundLogged::$request` is renamed to `$hit` of that same
  type — any listener reading `$event->request` directly needs updating.
- **Internal Links: a real link-detail endpoint.** `seo:internal-links`
  already wrote genuine source→target rows into `seo_internal_links`; the
  read API only ever exposed aggregated incoming/outgoing counts. New
  `GET {api.prefix}/internal-links/detail` (optionally `?type=`,
  `?locale=`) returns the actual rows, with each source's `seoUrl()`
  resolved batched by type rather than per row.
  Also fixed, found during this same audit: crawling one locale used to
  **delete another locale's rows** for the same record — the delete-before-
  insert step filtered only by `source_type`/`source_id`, with no way to
  address one locale's rows independently, since the table had no `locale`
  column at all. `seo_internal_links` gains a nullable `locale` column;
  `seo:internal-links` gains a `--locale=` option (temporarily switches the
  app locale while crawling, restored afterward — safe to call mid-request
  via `RunInternalLinksCommandTool`); both `internal-links` endpoints
  accept `?locale=` to keep incoming/outgoing counts from mixing rows a
  per-locale crawl kept separate on purpose.
- **Search Console: genuine time-series endpoints.** The sync command
  already stored one row per (url, date); the read API summed the whole
  requested window away before returning it, so no chart could be drawn
  from it. New `GET {api.prefix}/search-console/stats/timeseries?url=&days=`
  (day-by-day rows for one URL) and `GET {api.prefix}/search-console/stats/daily?days=`
  (site-wide daily totals, `GROUP BY date` instead of `index()`'s
  `GROUP BY url`). The existing `index()` aggregate is unchanged.
- **Dynamic Settings: operational config that was code-only.** Following
  this package's own "settable at runtime, no deploy" principle for
  anything an end-user has a legitimate ongoing reason to tune: added
  `not_found.enabled`, `not_found.sample_rate`, `not_found.max_rows`,
  `not_found.exclude`, `not_found.ingest_token` (secret), `redirects.enabled`,
  `redirects.eager`, `redirects.keep_query`, `redirects.allowed_hosts` to
  the allowlist. New validators: `IntegerSettingValidator`,
  `SampleRateSettingValidator` (0.0–1.0), `RegexListSettingValidator`
  (rejects the same nested-quantifier ReDoS shape `RedirectGuard` already
  refuses for a stored redirect rule — extracted into a shared
  `Support\CatastrophicPattern`), `HostListSettingValidator` (bare
  hostnames, distinct from the existing `UrlListSettingValidator` which
  requires a full `http(s)://` URL and doesn't fit
  `SameOriginUrls::allowedHosts()`'s bare-host comparison).

## 0.11.0 — 2026-09-09

Found by actually driving the app end-to-end (Testbench plus real HTTP
calls against seeded data) instead of assuming the read side kept up
with everything 0.10.0 added.

### Added

- **Dashboard broken-link and indexing counts.** The Dashboard — the
  first page an admin sees — never mentioned broken links or index
  status at all after `seo:broken-links` and
  `seo:search-console:inspect` shipped in 0.10.0; an admin had no way to
  know either existed without clicking into their own separate pages
  first. `DashboardController` (API, Panel, and the
  `seo.dashboard.summary` AI tool) gained `brokenLinksCount`
  (currently-broken external URLs) and `notIndexedCount` (URLs whose
  latest Search Console verdict isn't `PASS`) alongside the existing
  stats.
- **`ExternalSeoSignals`**, and `externalSignals` on every per-record
  read (`MetaController::show()`, the Blade panel's data endpoint,
  `seo.meta.get`). Editing one record showed only its live content
  score — nothing about whether that exact page has a bad PageSpeed
  score, isn't indexed, or cites a dead link, even though `seo:audit`
  already joins all three into a batch report. This is the same three
  lookups `seo:audit` already had, extracted out of `AuditCommand`
  rather than duplicated a third time, so the Blade panel, React's
  `SeoPanel`, Vue's `SeoPanel`, and the AI tool all show it identically.
  Null means "never checked", not "checked and fine" — the same
  distinction `seo:audit` itself already draws.

## 0.10.1 — 2026-09-09

### Fixed

- **`PublicUrlGuardTest`'s data provider**, unusable under PHPUnit 12 (what
  this package's own `composer.json` range resolves to): the
  `@dataProvider` doc-comment annotation was dropped in favour of the
  `#[DataProvider]` attribute, and PHPUnit 12 no longer falls back to the
  old form — it calls the test method with zero arguments instead, which
  threw an `ArgumentCountError`. Every other test across the new features
  in 0.10.0 passed on the first real run against this package's own test
  suite (only just possible locally, now that `duxbo/laravel-ai-core` is
  available to resolve); this was the one thing 0.10.0 shipped with a
  broken test file, caught the moment the suite could actually run.

## 0.10.0 — 2026-09-09

Fills the gaps between this package and a paid all-in-one SEO suite,
following the same headless shape every existing integration already has:
a client class, a console command, an AI tool behind its risk tier, a REST
endpoint, a Blade panel page, and a TypeScript SDK client method plus
React/Vue component.

### Added — Google Indexing, PageSpeed, and Search Console URL Inspection

- **`GoogleIndexingClient`** (`php artisan seo:google-indexing`,
  `seo.google_indexing.submit`) — signs its own JWT (RS256, via
  `openssl_sign`, no SDK) and exchanges it for an access token from a
  service account, mirroring how `SearchConsoleClient` never runs the OAuth
  consent flow itself. Google only documents the Indexing API for
  JobPosting/BroadcastEvent, but the endpoint accepts any URL; this package
  does not gate submission by content type, the same reasoning
  `IndexNowSubmitter` already applies.
- **`PageSpeedClient`** (`php artisan seo:pagespeed`, `seo.pagespeed.check`)
  — a plain API key (no OAuth needed for a public read-only API), separating
  lab data (always present) from field data (real Chrome UX Report
  visitors, only present once a URL has enough traffic).
- **`SearchConsoleClient::inspectUrl()`/`inspect()`**
  (`php artisan seo:search-console:inspect`, `seo.search_console.inspect`)
  — the URL Inspection API, reusing the same OAuth refresh-token flow the
  existing performance sync already has. Explicit URLs only: Google
  rate-limits this endpoint far tighter than search analytics
  (~2,000/day/site).

### Added — broken external link checking

`BrokenLinkChecker` (`php artisan seo:broken-links`,
`seo.broken_links.list`/`.console.broken_links`) crawls a model's external
links and checks each distinct URL once, split into `seo_external_links`
(who cites what) and `seo_link_checks` (is it still good) so the same URL
cited from fifty records is checked once, not fifty times. Every request —
including each redirect hop — is validated by a new `PublicUrlGuard` first,
rejecting literal private/loopback/link-local/reserved addresses and any
hostname that resolves to one: without it, a URL parsed out of a model's
own content (not trusted the way an operator's CLI arguments are) could
point at a cloud metadata endpoint or an internal service, turning a
scheduled crawl into an SSRF probe of the server's own network.

### Added — image sitemaps, tracking scripts, and AI alt-text suggestions

- **`HasSitemapImages`** — `<image:image>` support already existed in
  `SitemapWriter`, but nothing populated it for a real model;
  `ModelSource` now checks for this contract the same way it already does
  for `HasSitemapVideo`.
- **`Seo::trackingHead()`/`trackingBodyOpen()`**,
  `@seoTrackingHead`/`@seoTrackingBody` — a trusted, unparsed pass-through
  for GA4/GTM/Meta Pixel/TikTok Pixel snippets. Not SEO, but expected by
  agencies comparing this to Yoast/RankMath; both keys ride the existing
  dynamic-settings mechanism for free.
- **`SeoAiManager::suggestAltText()`** (`seo.analysis.suggest_alt_text`) —
  the one deliberately unlike every other AI suggestion here: nothing in
  the AI pipeline is multi-modal, so it infers alt text from a page's own
  content and each image's file name, never from what the image actually
  shows. The prompt says so outright. Propose-only, no apply tool, the
  same reason `suggestInternalLinkFixes()` has none: alt text lives inside
  a model's own body content.

### Changed — `seo:audit` now reports PageSpeed, indexing status, and broken links

Content score is still the only number the command computes itself — it
now also joins in PageSpeed, indexing status and broken-link count from
whatever the three features above last stored for each record's own URL,
never a live call of its own (a batch over every record of a model would
blow through Google's rate limits almost immediately). `null` rather than
`0` when nothing has ever checked that URL, so "no data yet" and "checked,
all good" are never conflated.

## 0.9.0 — 2026-09-07

Feature-complete and fully tested, but not yet 1.0. Nothing here has run in a
production site, and that is the only thing that turns a well-built package
into a hardened one — the edge cases that matter are the ones real projects
find. `Contracts/` is frozen at 1.0, so it stays open until then.

### Changed — the AI driver layer moved into `duxbo/laravel-ai-core`

`AiManager`, `AiBudget`, `AiCircuitBreaker`, the five REST drivers
(Claude/OpenAI/Gemini/Groq/OpenRouter), `AiRequest`/`AiResponse` and their
dynamic-settings/REST-API surface are no longer part of this package — they
now live in [`duxbo/laravel-ai-core`](https://github.com/Dungnecauoi/laravel-ai-core),
a new required dependency shared with any other package (a media package,
an agent runner) that also needs to talk to a model, so a fix or a new
provider is made in one place instead of once per package. This package's
own AI tool registry — `Contracts\AiTool`, `AiToolRegistry`,
`AiToolDispatcher`, the MCP server, all 27 tools — is unaffected and stays
here, since it is specific to what this package exposes, not to how a
request reaches a model.

**Breaking, for anyone already using the pre-1.0 `seo.ai.*` config:**
`seo.ai.default`, `seo.ai.drivers.*`, `seo.ai.cache_ttl`,
`seo.ai.daily_token_budget`, `seo.ai.circuit_breaker.*` and
`seo.ai.pricing.*` are gone — the equivalent keys now live in
`config/ai-core.php`. `seo_ai_log` is replaced by `ai-core`'s own
`ai_core_log` (scoped `profile = 'seo'` for this package's own calls);
since nothing here has run in production, there is no data to migrate.
`Seo::ai()` now returns a new `Ai\SeoAiManager` rather than `Ai\AiManager`
directly, but every existing call site (`suggestMeta()`, `suggestKeywords()`,
`suggestContentFixes()`, `suggestRedirectTarget()`,
`suggestInternalLinkFixes()`, `driver()`, `extend()`) keeps its exact same
signature. A project that wants SEO to use a different model or budget than
another package sharing the same `ai-core` install sets `config('seo.php')`'s
new `ai_overrides` key — see the README's "AI assistance" section.

Supported Laravel/testbench/PHPUnit versions are narrowed to match
`ai-core`'s own (Laravel 11–13; testbench 9–11; PHPUnit 10.5–12) rather than
the broader, untested 9–13 range this package's `composer.json` previously
allowed — the package's own Docker matrix never actually tested 9, 10 or 11
in the first place, both being past their security EOL.

### Added — a human confirms an AI proposal from the panel; console commands become AI tools

The two items the previous phase deliberately deferred:

- **Confirming a pending AI proposal from the Blade panel.** The "Hoạt động
  AI" page now shows proposals still inside their `proposal_ttl` window with
  no matching `applied` row, each with a "Xác nhận" button — a human
  reviewing and approving an AI's proposed action before it runs, the "human
  in the loop" MCP's own spec asks every client integration to provide.
  Confirming goes through the exact same `AiToolDispatcher::call()` every
  other caller does, built from *that request's own* signed-in user — it is
  not a bypass of `useSeoAiWrites`/`useSeoAiDestructive`. If those Gates
  still deny this user, confirming from the panel is refused exactly like
  confirming from an API or MCP call would be, with a friendly message
  instead of a 500. `AiToolDispatcher::propose()` now stores the preview
  text it already computes in a new `preview` column (added to the
  still-unreleased `seo_ai_tool_calls` table directly, not a follow-up
  migration — nothing has run this schema in production yet), since a
  reviewer needs something better to read than the raw `input` JSON.
- **Six console commands as AI tools**, honestly shaped: `Artisan::call()`
  only ever returns an exit code and whatever it printed, so
  `seo.console.audit`, `.internal_links`, `.sitemap`, `.search_console_sync`
  (Write), `.duplicates`, `.hreflang` (Read) return `{exitCode, output}`
  rather than a structured shape pretending to be richer than a terminal
  command actually is. `seo:indexnow` and `seo:prune-404` are deliberately
  not wrapped this way — `seo.indexnow.submit` and `seo.not_found.prune`
  already cover them with real structured output and a proper dry-run
  preview, which a console wrapper cannot offer.

### Added — visibility into AI activity: a panel page, a REST endpoint, UI components, a debug command

Closes out the AI tool registry work with the read-only visibility layer
promised alongside it — nothing here can mutate anything, it only shows
what the last four phases already built:

- **`GET /api/seo/v1/ai/tool-calls`** and its Blade panel twin at
  `/seo/panel/ai-tool-calls` — every propose and apply from
  `seo_ai_tool_calls`, newest first, same pagination shape as audit history
  and internal links.
- **`<SeoAiToolCalls>`** in both `@duxbo/seo-react` and `@duxbo/seo-vue` —
  the same read, for a project building its own admin surface instead of
  the Blade one. `@duxbo/seo-core`'s `SeoClient` gained `aiToolCalls()` and
  `aiTools()` (the manifest, for a UI that wants to show what an agent
  *could* call, not just what it already did).
- **`php artisan seo:ai:tools`** — lists every tool `AiToolRegistry` holds
  with its risk tier and description, for checking what an agent can see
  without making an HTTP or MCP call to find out.

Deliberately out of scope here: confirming a pending AI proposal *from* the
panel. That is a different, larger feature — a human reviewing and
approving an AI's proposed action — not a polish item on top of the
registry, and would need its own design pass (proposal expiry in the UI,
who is allowed to confirm what, an audit trail distinct from the AI's own).
This phase only makes what already happened visible.

### Added — grounded AI suggestions, meta tools, and a circuit breaker

Rounds out the tool registry (still no console-command wrapping — see the
first phase's notes on why) and gives the AI subsystem real capabilities
beyond the original `suggestMeta`/`suggestKeywords`, all grounded in actual
data rather than raw content alone:

- **`AiManager::suggestContentFixes()`** — the same `{title, description}`
  shape as `suggestMeta()`, but the prompt names the exact
  `AnalysisReport::problems()` findings (translated through the same
  `Translator` the panel uses, never a raw key like
  `seo::analysis.content_length.short`) so the model fixes what is actually
  wrong instead of writing generic meta from scratch.
- **`AiManager::suggestRedirectTarget()`** and **`suggestInternalLinkFixes()`**
  — both take a caller-built shortlist of *real* candidate URLs (ranked by a
  simple title/slug word-overlap heuristic, see `Ai\Tools\Concerns\RanksCandidatesByTitleOverlap`)
  and their JSON Schema constrains the answer to an `enum` of exactly those
  URLs — a model cannot hallucinate a redirect target or a link source no
  matter what the prompt alone asks for.
- **`PromptLibrary::meta()`** gained optional `$current`/`$siteBrand`/
  `$findings` parameters — grounding context appended as a clearly separate
  section, capped by a new `seo.ai.context_characters` (default 1000,
  distinct from `content_characters`). Passing none of them (every existing
  caller) produces byte-for-byte the same prompt as before.
- **Six new tools**: `seo.meta.suggest`/`.apply`/`.delete` (closing a gap
  from the tool registry's first phase — Meta only had a Read tool until
  now), `seo.analysis.suggest_fixes`, `seo.not_found.suggest_redirect_target`,
  `seo.internal_links.suggest_fixes`. `ApplyMetaTool` runs the same off-site
  canonical check `MetaController::update()` runs through Laravel's
  validator, reached directly since an AI tool call never goes through it.
- **`Ai\AiCircuitBreaker`** — after `seo.ai.circuit_breaker.threshold`
  (default 5) consecutive failures from one driver, it stops being tried at
  all for `cooldown_seconds` (default 60), so a loop over a few thousand
  records does not retry a provider that is already down once per record.
  Independent of `daily_token_budget`, which caps spend, not failure storms.
  Implements `ResetsBetweenRequests` like the package's other Octane-aware
  singletons, since the failure count is real cross-worker state.

Deliberately deferred, same reasoning as the console-command tools: batch
variants (a generator over many records checking the remaining daily budget
between items) and per-scope/per-tenant budget partitioning — both are
straightforward to add once there is a real caller shaped like one, and
building either speculatively risked guessing that shape wrong.

### Added — a REST manifest and an MCP endpoint for the AI tool registry

The tool registry from the last two phases was only reachable in-process
(`app(AiToolDispatcher::class)->call(...)`). Two thin protocol adapters over
the same registry, so any external AI agent or framework can reach it
without writing PHP:

- **`GET /api/seo/v1/ai/tools`** — the manifest, with both `input_schema`
  (Anthropic tool-use shape) and `parameters` (OpenAI function-calling
  shape) pointing at the identical JSON Schema, so one response serves
  either SDK's `tools` array as-is. **`POST /api/seo/v1/ai/tools/{name}/call`**
  — `{input, confirm}` reaching the same `AiToolDispatcher::call()` every
  in-process caller already goes through. Domain exceptions map to the HTTP
  status an existing caller of these repositories would expect:
  `AiToolNotFound` 404, `AiToolUnauthorized` 403, `AiToolProposalExpired` 409,
  any other `SeoException` (an unsafe redirect, an invalid setting value)
  422.
- **`POST /api/seo/v1/mcp`** — a hand-rolled MCP (Model Context Protocol)
  server speaking JSON-RPC 2.0 over the non-streaming half of the
  [Streamable HTTP transport](https://modelcontextprotocol.io/specification/2025-06-18/basic/transports):
  `initialize`, `ping`, `tools/list`, `tools/call`. No SSE, no session
  management, no resources/prompts/sampling — nothing here streams and every
  call is stateless, so none of that half of the spec is needed. Point
  Claude Code, Claude Desktop, or any other MCP client at this endpoint and
  every registered tool is immediately callable, with zero glue code written
  by the package or the installer. `GET`/`DELETE` on the same path answer
  405, exactly as the spec prescribes for a server that offers neither the
  server-initiated stream nor explicit session termination.

MCP's `tools/call` has only one `arguments` object, unlike the REST
endpoint's separate `input`/`confirm` fields — so for a Write/Destructive
tool, `arguments.confirm` carries the proposal id on the second call, making
`confirm` a reserved argument name no tool's own `inputSchema` should use.
A business-logic refusal from inside a tool (an unsafe redirect, a missing
record) is reported as a Tool Execution Error (`isError: true` inside a
normal JSON-RPC *result*), never a JSON-RPC *error* — matching the spec's
own split between protocol errors (unknown tool, bad params) and execution
errors (this call specifically failed).

### Added — write and destructive AI tools

Eight more tools on top of last phase's seven read-only ones, exercising the
propose/confirm cycle for real: `seo.redirects.create`, `.toggle`, `.delete`;
`seo.not_found.prune`, `.convert_to_redirect`; `seo.settings.set`, `.clear`;
`seo.indexnow.submit`. Every one is a thin wrapper over the same repository
its REST/panel twin already uses (`RedirectRepository`, `SettingsRepository`,
`NotFoundLogger`, `IndexNowSubmitter`) — none of them open a capability that
wasn't already reachable through the existing API or panel, they only make
it AI-callable.

Where it's cheap to know in advance, `preview()` runs the real check instead
of a generic description, so an obviously-bad call fails on the *propose*
step rather than wasting a confirm round trip: `CreateRedirectTool` and
`ConvertNotFoundToRedirectTool` run the same `RedirectGuard` pattern/target/
loop checks `RedirectRepository::create()` would; `SetSettingTool` runs
`SettingsRepository::assertValid()`; a secret setting's value is never
echoed into the preview text. `SubmitUrlsTool` is the deliberate exception —
its preview only describes the call, since actually validating it would mean
making the very outbound IndexNow request the propose/confirm split exists
to gate. `ToggleRedirectTool` takes an explicit desired `active` state
rather than "flip whatever it currently is," and re-enabling a rule that
would now form a loop is refused by `Redirect`'s own `saving` guard at
execute() time, the same as any other write to that model.

### Added — an AI tool registry, phase one (read-only)

The AI subsystem (`AiManager::suggestMeta()`/`suggestKeywords()`) has always
been reachable only by writing PHP against `Seo::ai()` directly — no route,
no console command, nothing an external agent could discover. This starts a
deeper integration: every capability of this package described as a
discrete, schema'd `Contracts\AiTool` an AI agent can enumerate and call,
without the package author hand-writing glue for whatever LLM SDK or agent
framework a host application happens to use.

- **`Ai\Tools\AiToolRegistry`** discovers tools from `seo.ai.tools.enabled`
  the same way `seo.analysis.checks` discovers content-analysis checks, and
  builds a manifest (name, description, JSON Schema input, risk tier) —
  filtered to only what the caller is actually authorized for, so a tool a
  caller cannot use is left off entirely rather than listed and refused.
- **Three risk tiers**, each behind its own Gate ability: `Read`
  (`viewSeoPanel`, same Gate as the rest of the API/panel), `Write`
  (`useSeoAiWrites`), `Destructive` (`useSeoAiDestructive`) — both new,
  deny-by-default like `viewSeoPanel` always has been. An application can
  let an agent read everything without also handing it delete.
- **`Ai\Tools\AiToolDispatcher`**: a `Read` tool runs immediately; a `Write`
  or `Destructive` tool requires two calls — the first returns a proposal id
  and a preview with nothing mutated, the second must name that proposal id
  to actually execute, replaying the input captured at propose time rather
  than trusting whatever the confirming call sends. Every propose and apply
  is logged to a new `seo_ai_tool_calls` table, independent of `seo_ai_log`
  (LLM token/cost accounting, unrelated to whether a tool call mutated
  anything).
- **Seven tools**, all `Read`-tier for now — thin wrappers with no logic of
  their own, calling the exact same services their REST/panel twins do:
  `seo.meta.get`, `seo.redirects.list`, `seo.not_found.list`,
  `seo.dashboard.summary`, `seo.audit.history`, `seo.internal_links.list`,
  `seo.settings.get` (same secret-masking as the dynamic settings API — a
  raw OAuth credential is never handed to an AI caller any more than a
  human one).

No REST or MCP endpoint yet — this phase proves the propose/manifest
plumbing against zero-risk tools before any mutation is reachable through
it. Write/Destructive tools, and the protocol surface itself, land next.

### Fixed — five robustness gaps found by an edge-case audit

- **Dynamic settings never validated the *value* being written**, only that
  the key was allowlisted. Concretely: `AiBudget::assertWithinBudget()`
  treated any limit `<= 0` as "unlimited," so a negative
  `ai.daily_token_budget` — written to *restrict* spend — silently disabled
  the cap instead; fixed so only an exact `0` means unlimited and a negative
  value fails closed. A new `Contracts\SettingValueValidator` plus one
  validator per allowlisted key (`seo.settings.validators`) closes the
  general gap: booleans must be booleans, URLs must be `http(s)` with a real
  host, `defaults.twitter.card` must be one `TwitterCard` actually defines,
  and `indexnow.key` is restricted to a route-safe charset — the value is
  embedded directly as a literal `Route::get($key.'.txt', ...)` segment, and
  a `{`/`}` in it would register a *dynamic* route parameter that matches
  any path in that position instead of the intended fixed one.
  `DynamicSettingsController::update()` now validates every key in a batch
  before writing any of them, the same all-or-nothing guarantee it already
  gave the allowlist check. A structural test asserts every entry in
  `seo.settings.keys` has a matching validator, so adding a key without one
  is a build failure, not a silent gap.
- **A redirect rule wasn't actually safe from every angle it claimed to
  be.** `Redirect`'s own docblock said hand-writing a row couldn't bypass
  `RedirectGuard`'s checks, but nothing enforced that — a seeder or
  `Redirect::create()` called directly skipped every check `RedirectRepository`
  runs. A `saving` model hook now re-runs them whenever a guarded column is
  dirty. Relatedly, `RedirectRepository::setActive()` re-enabled a rule with
  a bulk `update()` query, which never re-checked for a loop that could have
  formed from *other* rules changing while this one sat disabled;
  `disable()`, `setActive()` and `deleteById()` now fetch the model and call
  `save()`/`delete()` so the hook actually runs. (A raw query-builder mass
  update still bypasses it — Eloquent never fires model events for those, on
  this model or any other; the docblock now says so precisely instead of
  overclaiming.)
- **No canonical cycle detection.** Redirects have always refused a loop at
  write time; a canonical tag pointing A → B → A had nothing stopping it.
  Added `Canonical\CanonicalGuard`, wired into `Seo::save()`, with one case
  handled explicitly before anything else: a page canonicalizing to *itself*
  is the normal, correct case, not a cycle. Unlike a redirect rule, an
  arbitrary canonical URL has no guaranteed way back to the record that owns
  it, so the check is real but necessarily partial — it walks through a new
  `Contracts\CanonicalResolver`, bound by default to `NullCanonicalResolver`
  (always answers "unknown," a safe no-op). An application that can map its
  own URLs back to a record can bind a real resolver to turn the check on.
- **Content analysis broke on Chinese, Japanese and Thai.** `ContentLength`
  and `KeywordDensity` measured text by splitting on whitespace — exactly
  right for Vietnamese and a reasonable proxy for English, both of which
  actually separate words with spaces. Those three scripts don't: a whole
  article with no whitespace at all collapsed to a single "word," making
  content-length report "too short" no matter how much was written and
  keyword-density compute an impossible figure off a denominator of one. A
  new `Support\Text::isSpaceDelimitedScript()` detects which situation the
  text is in (by which kind of letter is *dominant*, so one quoted foreign
  word doesn't flip an otherwise-Vietnamese article), and `Analysis\Tokenizer`
  switches to counting letters instead of whitespace tokens when it isn't.
  `ContentLength` gets its own, explicitly heuristic
  `seo.analysis.content_length_cjk_minimum` (default 800) for the
  character-counted case, since a syllable-based threshold tuned for
  Vietnamese has no meaningful conversion to a character count.

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

### Added — read APIs and full UI for audit history, internal links, Search Console, IndexNow log

Four console commands (`seo:audit`, `seo:internal-links`,
`seo:search-console:sync`, and IndexNow's own logging) wrote data nothing
could read back except by querying the database directly. Added the read
side of each, then a view over it in all three UIs:

- `GET /api/seo/v1/audit-history` — batches newest first, filterable by model
- `GET /api/seo/v1/internal-links?type=X` — incoming/outgoing link counts
  per record, flagging zero incoming as an orphan
- `GET /api/seo/v1/search-console/stats?days=N` — clicks/impressions/position
  summed per URL over the window, not one row per day
- `GET /api/seo/v1/indexnow/log` — recent submissions, newest first

Blade gets four new pages (`/seo/panel/audit-history`, `/internal-links`,
`/search-console`, `/indexnow-log`) wired into the nav; React and Vue each
get four matching components (`SeoAuditHistory`, `SeoInternalLinks`,
`SeoSearchConsoleStats`, `SeoIndexNowLog`). All of them read-only — none of
the four runs the underlying command itself, the same reasoning that keeps
a real site crawl or an OAuth-backed API sync off a request a page load
waits on.

Caught during this batch: two React components rendered `{expression} literal text`
as JSX, which produces two separate text nodes rather than one concatenated
string — invisible in a browser, which merges adjacent text nodes on its
own, but exactly the kind of thing a substring-matching test assertion
exposes. Fixed to a single template-literal expression in both places, and
the tests that caught it stay as the regression coverage.

### Added — an edit form for dynamic settings, in all three UIs

Dynamic settings shipped with a REST API and no UI at all — a project had to
build its own settings page to use it. All three UIs' Settings surface now
does that: the same read-only status stays at the top, and when
`seo.settings.enabled` is true, an edit form for every allowlisted key
appears below it, grouped the same way in Blade, React and Vue — general,
meta defaults, verification, robots & schema, IndexNow, Search Console.
Saved immediately over the same `SettingsRepository` / `/dynamic-settings`
API the last two entries in this file built. A secret field (client secret,
refresh token) shows only whether one `is_set`, never its value, with a
blank input meaning "leave it" rather than "clear it" — the same contract
the API already made.

Caught by a real test, not just written and assumed correct: the Blade
view's first draft used `data_get($dynamicSettings, "{$key}.value")`, which
splits its path on every dot — the wrong tool for an array keyed by dotted
strings like `'defaults.title'` as one flat key rather than a nested
structure, and it silently returned null for every setting but the two
whose name happened to contain no dot. Fixed to plain array access; the
failing assertion this surfaced remains committed as a regression test.

### Added — Groq and OpenRouter AI drivers

Both speak OpenAI's own Chat Completions shape, which OpenAI itself,
`OpenAiDriver` and now `GroqDriver` / `OpenRouterDriver` all share through
a new `OpenAiCompatibleDriver` — `OpenAiDriver` used to carry that request
and response handling directly, but `final class` blocked reuse by
inheritance, so it moved to a common (non-final) base the way `HttpDriver`
already sits under all four HTTP-based drivers. Reached the same way as
every other driver: `seo.ai.drivers.groq` / `.openrouter`, a `key`, and a
`model` — one that actually honours `response_format: json_schema`, since
structured-output support depends on the underlying model, not on either
platform. `OpenRouterDriver` additionally sends the optional `HTTP-Referer`
/ `X-Title` headers OpenRouter's own docs ask for, when `referer` / `title`
are configured.

### Fixed — `og.image` / `twitter.image` disappeared for any record that set another `og.*` field

`SeoData::fillMissingFrom()` merged `openGraph` and `twitter` as whole objects
— `$this->openGraph ?? $fallback->openGraph` — instead of field by field. The
resolution pipeline builds a non-null `OpenGraphData` the moment a record maps
even one `og.*` key, so a post that maps only `og.title` (the common case)
already carried a "decided" `OpenGraphData` with `image` null by the time a
later stage — a model-attribute mapping, or a site-wide `seo.defaults`
override — tried to supply an image. The whole-object fallback saw the
earlier object as final and discarded the later one outright, image included.
Every stage boundary in the pipeline (`StoredValue` → `ModelAttribute` →
`Template` → `GlobalDefault`) was affected, not only the last one. Fixed by
giving `OpenGraphData` and `TwitterData` their own `fillMissingFrom()` that
merges per field, the same way `SeoData` already treats its own scalar
fields, so a record can decide `og.title` on its own while still inheriting
`og.image` from whichever later stage sets one.

### Fixed — dynamic settings could echo back an OAuth client secret and refresh token

`GET /api/seo/v1/dynamic-settings` returned the literal value of every
allowlisted key, `search_console.client_secret` and
`search_console.refresh_token` included — gated by the same Gate as the rest
of the API, so not publicly reachable, but still a real credential exposed
to anyone with panel access rather than only whoever set it. A new
`seo.settings.secret_keys` list (currently those two) makes `SettingsRepository`
and `DynamicSettingsController::index()` report `{ is_set, overridden, secret: true }`
for a secret key instead of its value — still writable through the same
`PUT`, never readable back afterward, the same way GitHub or Stripe never
show a generated secret a second time. `indexnow.key` and
`search_console.client_id` are deliberately not on that list: the first is
published on purpose at `/{key}.txt`, the second routinely visible in a
browser's own OAuth redirect URL — neither is actually secret.

### Fixed — three singletons that went stale under Octane and long-running queue workers

Auditing every singleton this package registers found three that cache
something in an instance property rather than only in Laravel's own `Cache`
store — invisible under ordinary PHP-FPM, where the whole container is
rebuilt fresh every request, but a real bug under Laravel Octane or a
long-running `queue:work` process, where the same instance persists across
many requests or jobs:

- `CachedRedirectMatcher` — the worst of the three. Its `$rules` property is
  its own shortcut on top of the shared cache `flush()` already invalidates
  correctly; under a persistent worker, an edit made through *another*
  worker's process calls `flush()` on a *different* instance, leaving this
  one still serving the redirect list from before the edit — indefinitely,
  since nothing ever told it to check again.
- `AiManager` — memoized driver instances hold whatever `seo.ai.drivers.*`
  said the *first* time each was resolved, baked in at construction; a
  config change afterward (through the new dynamic settings feature, or
  simply a deploy that changed an env var picked up mid-process by
  something else) never reaches an already-built driver.
- `SettingsRepository` (added just above) — `applyToConfig()` was only ever
  called once, at boot; under a worker that stays booted for its whole life,
  "once at boot" means "once, ever, until the worker restarts," and a
  setting saved through the dynamic-settings API would never reach it.

Fixed with a new `Contracts\ResetsBetweenRequests` interface each of the
three now implements, and two listeners the service provider registers by
event class *name* as a plain string — `Laravel\Octane\Events\RequestReceived`
and `Illuminate\Queue\Events\JobProcessing` — rather than importing either
class. Neither `laravel/octane` nor `illuminate/queue` becomes a dependency
of this package this way: on a runtime where the matching event does not
exist, the listener simply never fires.

### Added — dynamic settings, backed by an API

`config/seo.php` was, until now, the only way to change anything — a file,
requiring a deploy. `seo.settings.enabled = true` opts a project into
`seo_settings`, a table `SettingsRepository::applyToConfig()` reads once at
boot and pushes straight into Laravel's own config repository, before
anything else in the package reads a single `seo.*` key. Every existing
consumer — `HtmlFormatter`, `RobotsTxt`, `GlobalDefaultStage`, the
verification and IndexNow code added earlier in this same file — needed
zero changes to support this: they already read `config()`, and this only
changes what that call returns.

Only the dot-notated keys listed in the new `seo.settings.keys` can ever be
written through `SettingsRepository::set()` — arbitrary keys are rejected
with `UnknownSetting`, the same allowlist-not-guess reasoning `seo.api.models`
already uses for which model types the API can touch. Exposed over
`/api/seo/v1/dynamic-settings` (`GET`/`PUT`/`DELETE`) behind the same Gate as
the rest of the REST API — this package ships no settings-page UI itself,
since the API existing at all is what lets a project's own front end build
one without either side needing to know the other exists. Off by default,
and a missing `seo_settings` table (a fresh install that never migrated it)
is treated as "no overrides" rather than a fatal error, the same defensive
handling already used elsewhere in this file for a table that might not
exist yet.

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
