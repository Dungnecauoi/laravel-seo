import { SeoApiError, SeoTimeoutError } from './errors.js'
import type {
  AnalysisReport,
  ContentListResponse,
  DashboardStats,
  DynamicSettingsResponse,
  MetaResponse,
  NotFoundEntry,
  OutputFormat,
  RedirectInput,
  RedirectListResponse,
  ResolvedMeta,
  SeoClientOptions,
  SeoData,
  SettingsResponse,
} from './types.js'

/** The API contract this client was written against. */
export const CONTRACT_VERSION = '1'

export interface SeoClient {
  resolve(url: string, options?: { locale?: string; format?: OutputFormat }): Promise<ResolvedMeta>
  analyze(input: AnalyzeInput): Promise<AnalysisReport>
  getMeta(type: string, id: string | number, locale?: string): Promise<MetaResponse>
  saveMeta(type: string, id: string | number, data: SeoData & { locale?: string }): Promise<{ resolved: SeoData }>
  deleteMeta(type: string, id: string | number, locale?: string): Promise<void>
  notFound(limit?: number): Promise<NotFoundEntry[]>
  deleteNotFound(id: number): Promise<void>
  pruneNotFound(days?: number): Promise<{ deleted: number }>
  convertNotFoundToRedirect(id: number, target: string): Promise<{ id: number }>

  dashboard(): Promise<DashboardStats>
  content(type?: string, page?: number): Promise<ContentListResponse>
  settings(): Promise<SettingsResponse>

  redirects(page?: number): Promise<RedirectListResponse>
  createRedirect(input: RedirectInput): Promise<{ id: number }>
  toggleRedirect(id: number): Promise<{ isActive: boolean }>
  deleteRedirect(id: number): Promise<void>

  dynamicSettings(): Promise<DynamicSettingsResponse>
  /** @param settings Dot-notated key => value, e.g. `{ 'verification.google': 'abc123' }`. */
  updateDynamicSettings(settings: Record<string, unknown>): Promise<{ saved: string[] }>
  deleteDynamicSetting(key: string): Promise<{ cleared: string }>
}

export interface AnalyzeInput {
  content: string
  keyword?: string
  title?: string
  description?: string
  url?: string
  locale?: string
}

export function createSeoClient(options: SeoClientOptions): SeoClient {
  const {
    baseUrl,
    token,
    prefix = 'api/seo/v1',
    headers = {},
    fetch: fetchImpl = globalThis.fetch,
    timeout = 15_000,
  } = options

  if (typeof fetchImpl !== 'function') {
    throw new Error('No fetch implementation available. Pass one via options.fetch on Node < 18.')
  }

  const root = `${baseUrl.replace(/\/+$/, '')}/${prefix.replace(/^\/+|\/+$/g, '')}`

  let warnedAboutContract = false

  async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
    // AbortController rather than Promise.race: the request is actually
    // cancelled, instead of being left running with nobody listening.
    const controller = new AbortController()
    const timer = setTimeout(() => controller.abort(), timeout)

    let response: Response

    try {
      response = await fetchImpl(`${root}/${path.replace(/^\/+/, '')}`, {
        ...init,
        signal: controller.signal,
        headers: {
          Accept: 'application/json',
          ...(init.body ? { 'Content-Type': 'application/json' } : {}),
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
          ...headers,
          ...(init.headers as Record<string, string> | undefined),
        },
      })
    } catch (error) {
      clearTimeout(timer)

      if (error instanceof Error && error.name === 'AbortError') {
        throw new SeoTimeoutError(timeout)
      }

      throw error
    }

    clearTimeout(timer)

    // A mismatch means the backend moved on without this client. Warn once —
    // silently returning a shape the caller does not expect is worse.
    const contract = response.headers.get('X-Seo-Contract')

    if (contract && contract !== CONTRACT_VERSION && !warnedAboutContract) {
      warnedAboutContract = true
      console.warn(
        `[@duxbo/seo-core] The API reports contract v${contract}, this client speaks v${CONTRACT_VERSION}. ` +
          'Update the package whose major.minor is behind.',
      )
    }

    const body = response.status === 204 ? null : await response.json().catch(() => null)

    if (!response.ok) {
      const message =
        (body && typeof body === 'object' && 'message' in body && typeof body.message === 'string'
          ? body.message
          : null) ?? `The SEO API returned HTTP ${response.status}.`

      throw new SeoApiError(message, response.status, body)
    }

    return body as T
  }

  function query(params: Record<string, string | number | undefined>): string {
    const search = new URLSearchParams()

    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined) search.set(key, String(value))
    }

    const string = search.toString()

    return string ? `?${string}` : ''
  }

  return {
    resolve(url, { locale, format } = {}) {
      return request(`resolve${query({ url, locale, format })}`)
    },

    analyze(input) {
      return request('analyze', { method: 'POST', body: JSON.stringify(input) })
    },

    getMeta(type, id, locale) {
      return request(`meta/${encodeURIComponent(type)}/${encodeURIComponent(String(id))}${query({ locale })}`)
    },

    saveMeta(type, id, data) {
      return request(`meta/${encodeURIComponent(type)}/${encodeURIComponent(String(id))}`, {
        method: 'PUT',
        body: JSON.stringify(data),
      })
    },

    async deleteMeta(type, id, locale) {
      await request(`meta/${encodeURIComponent(type)}/${encodeURIComponent(String(id))}${query({ locale })}`, {
        method: 'DELETE',
      })
    },

    async notFound(limit) {
      const response = await request<{ data: NotFoundEntry[] }>(`not-found${query({ limit })}`)

      return response.data
    },

    async deleteNotFound(id) {
      await request(`not-found/${id}`, { method: 'DELETE' })
    },

    pruneNotFound(days) {
      return request('not-found/prune', { method: 'POST', body: JSON.stringify({ days }) })
    },

    convertNotFoundToRedirect(id, target) {
      return request(`not-found/${id}/redirect`, { method: 'POST', body: JSON.stringify({ target }) })
    },

    dashboard() {
      return request('dashboard')
    },

    content(type, page) {
      return request(`content${query({ type, page })}`)
    },

    settings() {
      return request('settings')
    },

    redirects(page) {
      return request(`redirects${query({ page })}`)
    },

    createRedirect(input) {
      return request('redirects', { method: 'POST', body: JSON.stringify(input) })
    },

    toggleRedirect(id) {
      return request(`redirects/${id}/toggle`, { method: 'PATCH' })
    },

    async deleteRedirect(id) {
      await request(`redirects/${id}`, { method: 'DELETE' })
    },

    dynamicSettings() {
      return request('dynamic-settings')
    },

    updateDynamicSettings(settings) {
      return request('dynamic-settings', { method: 'PUT', body: JSON.stringify({ settings }) })
    },

    deleteDynamicSetting(key) {
      return request(`dynamic-settings/${encodeURIComponent(key)}`, { method: 'DELETE' })
    },
  }
}
