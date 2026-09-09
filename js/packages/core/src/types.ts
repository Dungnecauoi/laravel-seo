/**
 * Shapes returned by the Laravel package's `/api/seo/v1` endpoints.
 *
 * These mirror the PHP DTOs. The API version is independent of the package
 * version, so this file only changes when the contract does.
 */

export type CheckStatus = 'pass' | 'warning' | 'fail' | 'skipped'

export interface OpenGraphData {
  title?: string
  description?: string
  image?: string
  imageAlt?: string
  imageWidth?: number
  imageHeight?: number
  type?: string
  url?: string
  siteName?: string
  locale?: string
  alternateLocales?: string[]
}

export interface TwitterData {
  card?: 'summary' | 'summary_large_image' | 'app' | 'player'
  site?: string
  creator?: string
  title?: string
  description?: string
  image?: string
  imageAlt?: string
}

export interface SeoData {
  title?: string
  description?: string
  canonical?: string
  /** Already rendered as a meta tag value, e.g. "noindex, nofollow". */
  robots?: string
  openGraph?: OpenGraphData
  twitter?: TwitterData
  focusKeyword?: string
  secondaryKeywords?: string[]
  score?: number
  extra?: Record<string, unknown>
}

export interface ResolvedMeta extends SeoData {
  url: string
  locale: string | null
}

export interface CheckResult {
  id: string
  status: CheckStatus
  /** A translation key; the API resolves it when a locale is set. */
  message: string
  hint: string | null
  context: Record<string, unknown>
}

export interface AnalysisReport {
  /** 0-100. Skipped checks are excluded from the calculation entirely. */
  score: number
  locale: string | null
  results: CheckResult[]
}

/**
 * The same three "has anything already checked this page" signals a batch
 * `seo:audit` joins in, for one specific record — never a live check of its
 * own, only whatever `seo:pagespeed` / `seo:search-console:inspect` /
 * `seo:broken-links` already stored. `null` on any field means "never
 * checked", not "checked and fine".
 */
export interface ExternalSeoSignals {
  pagespeedScore: number | null
  gscVerdict: string | null
  brokenLinksCount: number | null
}

export interface MetaResponse {
  /** What was entered, or null when nothing has been. */
  stored: SeoData | null
  /** What will actually be published, after the fallback chain runs. */
  resolved: SeoData
  locales: string[]
  externalSignals: ExternalSeoSignals
}

export interface NotFoundEntry {
  id: number
  /** Already HTML-escaped by the API: this is attacker-supplied text. */
  path: string
  hits: number
  referrer: string | null
  user_agent: string | null
  first_seen_at: string | null
  last_seen_at: string | null
}

/**
 * Output shapes the API can format into. `next` returns the object Next.js
 * `generateMetadata()` expects; `nuxt` and `vue` return an Unhead payload.
 */
export type OutputFormat = 'array' | 'html' | 'jsonld' | 'next' | 'nuxt' | 'vue'

/** Shapes for the admin-shell endpoints — the dashboard, content list,
 * redirects and settings a React/Vue front end builds on instead of the
 * Blade panel. */

export interface DashboardStats {
  seoEnabled: boolean
  totalRecords: number
  missingByType: Record<string, number>
  totalMissing: number
  activeRedirects: number
  notFoundCount: number
  sitemapSources: number
  /** Currently-known-broken external URLs, from the last `seo:broken-links` run. */
  brokenLinksCount: number
  /** URLs whose latest Search Console verdict isn't PASS, from the last `seo:search-console:inspect` run. */
  notIndexedCount: number
  exposedTypes: string[]
}

export interface ContentRow {
  id: string | number
  title: string | null
  description: string | null
  robots: string | null
  url: string
}

export interface PageMeta {
  currentPage: number
  lastPage: number
  total: number
}

export interface ContentListResponse {
  exposedTypes: string[]
  type: string | null
  data: ContentRow[]
  meta: PageMeta | null
}

export type RedirectMatchType = 'exact' | 'prefix' | 'regex'
export type RedirectStatus = 301 | 302 | 307 | 308 | 410 | 451

export interface RedirectEntry {
  id: number
  source: string
  target: string | null
  type: RedirectMatchType
  status: RedirectStatus
  isActive: boolean
  locale: string | null
  notes: string | null
  hits: number
}

export interface RedirectInput {
  source: string
  target?: string | null
  type: RedirectMatchType
  status: RedirectStatus
  locale?: string | null
  notes?: string | null
}

export interface RedirectListResponse {
  data: RedirectEntry[]
  meta: PageMeta
}

export interface SettingsResponse {
  seoEnabled: boolean
  indexableEnvironments: string[]
  currentEnvironment: string
  apiEnabled: boolean
  panelEnabled: boolean
  exposedModels: string[]
  allowedHosts: string[]
  sitemapSourceCount: number
  aiDriver: string
  aiBudget: number
  analysisRateLimit: string
  supportedLocales: string[]
}

/**
 * One allowlisted key's current state — a config-file value unless
 * `overridden` is true. A secret key (an OAuth client secret, a refresh
 * token) never carries `value`, only whether one `is_set`; the API does not
 * echo a secret back once it is written.
 */
export type DynamicSettingValue =
  | { value: unknown; default: unknown; overridden: boolean; secret: false }
  | { is_set: boolean; overridden: boolean; secret: true }

export interface DynamicSettingsResponse {
  enabled: boolean
  settings: Record<string, DynamicSettingValue>
}

/**
 * One `php artisan seo:audit` run. `averageScore`/`minScore`/`maxScore` are
 * always computed from real content analysis. `averagePagespeedScore` /
 * `recordsNotIndexed` / `recordsWithBrokenLinks` are joined in from whatever
 * `seo:pagespeed` / `seo:search-console:inspect` / `seo:broken-links` had
 * already stored for these records' URLs — `null` means "never checked",
 * not "checked and fine".
 */
export interface AuditBatchEntry {
  id: number
  model: string
  locale: string | null
  totalRecords: number
  averageScore: number | null
  minScore: number | null
  maxScore: number | null
  averagePagespeedScore: number | null
  recordsNotIndexed: number | null
  recordsWithBrokenLinks: number | null
  startedAt: string | null
  finishedAt: string | null
}

export interface AuditHistoryResponse {
  data: AuditBatchEntry[]
  meta: PageMeta
}

/** One record's internal-link count, from `php artisan seo:internal-links`. */
export interface InternalLinkRow {
  id: string | number
  url: string
  incomingLinks: number
  outgoingLinks: number
  isOrphan: boolean
}

export interface InternalLinksResponse {
  exposedTypes: string[]
  type: string | null
  data: InternalLinkRow[]
  meta: PageMeta | null
}

/** One page's Search Console performance, summed over the requested window. */
export interface SearchConsoleStatRow {
  url: string
  clicks: number
  impressions: number
  ctr: number
  position: number | null
}

export interface SearchConsoleStatsResponse {
  days: number
  totalClicks: number
  totalImpressions: number
  data: SearchConsoleStatRow[]
}

/** One IndexNow API call — a batch of URLs, not one entry per URL. */
export interface IndexNowLogEntry {
  id: number
  urls: string[]
  urlCount: number
  successful: boolean
  statusCode: number | null
  error: string | null
  createdAt: string | null
}

export interface IndexNowLogResponse {
  data: IndexNowLogEntry[]
}

/**
 * One Google Indexing API call — one row per URL, unlike IndexNow's one row
 * per batch, since the Indexing API itself only ever takes one URL per call.
 */
export interface GoogleIndexingLogEntry {
  id: number
  url: string
  type: 'URL_UPDATED' | 'URL_DELETED'
  successful: boolean
  statusCode: number | null
  error: string | null
  createdAt: string | null
}

export interface GoogleIndexingLogResponse {
  data: GoogleIndexingLogEntry[]
}

/**
 * The latest PageSpeed Insights run for one URL+strategy — lab metrics from
 * a synthetic Lighthouse run, plus field data (real Chrome UX Report
 * visitors) when the URL has enough traffic for Google to have collected
 * it. `fieldDataAvailable: false` is not a failure, just "not enough data".
 */
export interface PageSpeedStatRow {
  url: string
  strategy: 'mobile' | 'desktop'
  performanceScore: number | null
  lcpMs: number | null
  clsScore: number | null
  tbtMs: number | null
  fcpMs: number | null
  speedIndexMs: number | null
  fieldDataAvailable: boolean
  cwvCategory: 'FAST' | 'AVERAGE' | 'SLOW' | null
  date: string
}

export interface PageSpeedStatsResponse {
  strategy: string
  data: PageSpeedStatRow[]
}

/**
 * The latest URL Inspection result for one URL — whether Google has it
 * indexed, and why not if it doesn't. `mobileUsabilityIssues` is a list of
 * issue-type codes (e.g. `TEXT_TOO_SMALL`), not full descriptions — Google's
 * API returns codes, and this SDK does not localize or explain them.
 */
export interface UrlInspectionRow {
  url: string
  verdict: 'PASS' | 'PARTIAL' | 'FAIL' | 'NEUTRAL' | null
  coverageState: string | null
  robotsTxtState: string | null
  indexingState: string | null
  pageFetchState: string | null
  googleCanonical: string | null
  userCanonical: string | null
  mobileUsabilityVerdict: 'PASS' | 'FAIL' | 'NEUTRAL' | null
  mobileUsabilityIssues: string[]
  richResultsVerdict: 'PASS' | 'FAIL' | 'NEUTRAL' | null
  date: string
}

export interface UrlInspectionsResponse {
  data: UrlInspectionRow[]
}

/** One record citing a broken URL. */
export interface BrokenLinkSource {
  sourceType: string
  sourceId: string
  anchorText: string | null
}

/**
 * One currently-known-broken external URL, with which records cite it —
 * checked once per distinct URL, not once per citing record.
 */
export interface BrokenLinkRow {
  url: string
  statusCode: number | null
  error: string | null
  checkedAt: string | null
  sources: BrokenLinkSource[]
}

export interface BrokenLinksResponse {
  data: BrokenLinkRow[]
  meta: { currentPage: number; lastPage: number; total: number }
}

/** One AI tool call, propose and apply alike — from `seo_ai_tool_calls`. */
export interface AiToolCallEntry {
  id: number
  tool: string
  riskTier: 'read' | 'write' | 'destructive'
  status: 'proposed' | 'applied'
  proposalId: string | null
  input: Record<string, unknown>
  output: Record<string, unknown> | null
  preview: string | null
  scope: string | null
  createdAt: string | null
  appliedAt: string | null
}

export interface AiToolCallsResponse {
  data: AiToolCallEntry[]
  meta: PageMeta
}

/** One entry in the AI tool manifest — {@see AiToolCallsResponse} is the log of calls, this is what can be called. */
export interface AiToolManifestEntry {
  name: string
  description: string
  /** Anthropic tool-use shape. */
  input_schema: Record<string, unknown>
  /** OpenAI function-calling shape — identical schema, different key. */
  parameters: Record<string, unknown>
  risk_tier: 'read' | 'write' | 'destructive'
}

export interface AiToolManifestResponse {
  tools: AiToolManifestEntry[]
}

export interface SeoClientOptions {
  /** Origin of the Laravel application, without a trailing slash. */
  baseUrl: string
  /** Bearer token. The SEO API denies everyone by default. */
  token?: string
  /** Defaults to 'api/seo/v1'. */
  prefix?: string
  headers?: Record<string, string>
  fetch?: typeof globalThis.fetch
  /** Milliseconds. Defaults to 15000. */
  timeout?: number
}
