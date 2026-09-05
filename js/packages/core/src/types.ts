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

export interface MetaResponse {
  /** What was entered, or null when nothing has been. */
  stored: SeoData | null
  /** What will actually be published, after the fallback chain runs. */
  resolved: SeoData
  locales: string[]
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
