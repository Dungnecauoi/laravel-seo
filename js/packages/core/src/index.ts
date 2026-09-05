export { createSeoClient, CONTRACT_VERSION } from './client.js'
export type { SeoClient, AnalyzeInput } from './client.js'

export { createMetaStore } from './store.js'
export type { MetaStore, MetaStoreOptions, MetaStoreTarget } from './store.js'

export { SeoApiError, SeoTimeoutError } from './errors.js'

export type {
  AnalysisReport,
  AuditBatchEntry,
  AuditHistoryResponse,
  CheckResult,
  CheckStatus,
  ContentListResponse,
  ContentRow,
  DashboardStats,
  DynamicSettingValue,
  DynamicSettingsResponse,
  IndexNowLogEntry,
  IndexNowLogResponse,
  InternalLinkRow,
  InternalLinksResponse,
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
  SearchConsoleStatRow,
  SearchConsoleStatsResponse,
  SeoClientOptions,
  SeoData,
  SettingsResponse,
  TwitterData,
} from './types.js'
