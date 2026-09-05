export { createSeoClient, CONTRACT_VERSION } from './client.js'
export type { SeoClient, AnalyzeInput } from './client.js'

export { createMetaStore } from './store.js'
export type { MetaStore, MetaStoreOptions, MetaStoreTarget } from './store.js'

export { SeoApiError, SeoTimeoutError } from './errors.js'

export type {
  AnalysisReport,
  CheckResult,
  CheckStatus,
  ContentListResponse,
  ContentRow,
  DashboardStats,
  DynamicSettingValue,
  DynamicSettingsResponse,
  MetaResponse,
  NotFoundEntry,
  OpenGraphData,
  OutputFormat,
  PageMeta,
  RedirectEntry,
  RedirectInput,
  RedirectListResponse,
  RedirectMatchType,
  RedirectStatus,
  ResolvedMeta,
  SeoClientOptions,
  SeoData,
  SettingsResponse,
  TwitterData,
} from './types.js'
