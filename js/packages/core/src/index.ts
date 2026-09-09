export { createSeoClient, CONTRACT_VERSION } from './client.js'
export type { SeoClient, AnalyzeInput } from './client.js'

export { createMetaStore } from './store.js'
export type { MetaStore, MetaStoreOptions, MetaStoreTarget } from './store.js'

export { SeoApiError, SeoTimeoutError } from './errors.js'

export type {
  AiToolCallEntry,
  AiToolCallsResponse,
  AiToolManifestEntry,
  AiToolManifestResponse,
  AnalysisReport,
  AuditBatchEntry,
  AuditHistoryResponse,
  BrokenLinkRow,
  BrokenLinkSource,
  BrokenLinksResponse,
  CheckResult,
  CheckStatus,
  ContentListResponse,
  ContentRow,
  DashboardStats,
  DynamicSettingValue,
  DynamicSettingsResponse,
  GoogleIndexingLogEntry,
  GoogleIndexingLogResponse,
  IndexNowLogEntry,
  IndexNowLogResponse,
  InternalLinkRow,
  InternalLinksResponse,
  MetaResponse,
  NotFoundEntry,
  OpenGraphData,
  OutputFormat,
  PageMeta,
  PageSpeedStatRow,
  PageSpeedStatsResponse,
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
  UrlInspectionRow,
  UrlInspectionsResponse,
} from './types.js'
