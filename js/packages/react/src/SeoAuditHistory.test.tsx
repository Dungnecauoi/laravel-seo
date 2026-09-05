import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { AuditHistoryResponse, SeoClient } from '@duxbo/seo-core'
import { SeoAuditHistory } from './SeoAuditHistory.js'

function stubClient(auditHistory: (model?: string) => Promise<AuditHistoryResponse>): SeoClient {
  return {
    resolve: async () => ({ url: '/x', locale: null }),
    analyze: async () => ({ score: 0, locale: null, results: [] }),
    getMeta: async () => ({ stored: null, resolved: {}, locales: [] }),
    saveMeta: async (_t, _i, data) => ({ resolved: data }),
    deleteMeta: async () => {},
    notFound: async () => [],
    deleteNotFound: async () => {},
    pruneNotFound: async () => ({ deleted: 0 }),
    convertNotFoundToRedirect: async () => ({ id: 0 }),
    dashboard: async () => ({
      seoEnabled: true,
      totalRecords: 0,
      missingByType: {},
      totalMissing: 0,
      activeRedirects: 0,
      notFoundCount: 0,
      sitemapSources: 0,
      exposedTypes: [],
    }),
    content: async () => ({ exposedTypes: [], type: null, data: [], meta: null }),
    settings: async () => ({
      seoEnabled: true,
      indexableEnvironments: [],
      currentEnvironment: 'testing',
      apiEnabled: true,
      panelEnabled: false,
      exposedModels: [],
      allowedHosts: [],
      sitemapSourceCount: 0,
      aiDriver: 'null',
      aiBudget: 0,
      analysisRateLimit: '30,1',
      supportedLocales: [],
    }),
    redirects: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
    createRedirect: async () => ({ id: 1 }),
    toggleRedirect: async () => ({ isActive: true }),
    deleteRedirect: async () => {},
    dynamicSettings: async () => ({ enabled: false, settings: {} }),
    updateDynamicSettings: async (settings) => ({ saved: Object.keys(settings) }),
    deleteDynamicSetting: async (key) => ({ cleared: key }),
    auditHistory,
    internalLinks: async () => ({ exposedTypes: [], type: null, data: [], meta: null }),
    searchConsoleStats: async () => ({ days: 30, totalClicks: 0, totalImpressions: 0, data: [] }),
    indexNowLog: async () => ({ data: [] }),
  }
}

test('shows the empty state with no batches', async () => {
  const client = stubClient(async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoAuditHistory client={client} />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('Chưa có lần audit'))
})

test('renders a batch with its average score and record count', async () => {
  const client = stubClient(async () => ({
    data: [
      {
        id: 1,
        model: 'App\\Models\\Post',
        locale: null,
        totalRecords: 12,
        averageScore: 72.5,
        minScore: 40,
        maxScore: 90,
        startedAt: '2026-01-01T00:00:00Z',
        finishedAt: '2026-01-01T00:01:00Z',
      },
    ],
    meta: { currentPage: 1, lastPage: 1, total: 1 },
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoAuditHistory client={client} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('Post'))
  assert.ok(json.includes('72.5'))
  assert.ok(json.includes('12'))
})

test('passes the model filter through to the client', async () => {
  let seen: string | undefined
  const client = stubClient(async (model) => {
    seen = model
    return { data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }
  })

  await act(async () => {
    create(<SeoAuditHistory client={client} model="App\Models\Post" />)
  })

  assert.equal(seen, 'App\\Models\\Post')
})
