import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { IndexNowLogResponse, SeoClient } from '@duxbo/seo-core'
import { SeoIndexNowLog } from './SeoIndexNowLog.js'

function stubClient(indexNowLog: () => Promise<IndexNowLogResponse>): SeoClient {
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
    auditHistory: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
    internalLinks: async () => ({ exposedTypes: [], type: null, data: [], meta: null }),
    searchConsoleStats: async () => ({ days: 30, totalClicks: 0, totalImpressions: 0, data: [] }),
    indexNowLog,
  }
}

test('shows the empty state with no submissions', async () => {
  const client = stubClient(async () => ({ data: [] }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoIndexNowLog client={client} />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('Chưa có lần gửi nào'))
})

test('renders a failed submission with its status and error as a tooltip', async () => {
  const client = stubClient(async () => ({
    data: [
      {
        id: 1,
        urls: ['/a', '/b'],
        urlCount: 2,
        successful: false,
        statusCode: 403,
        error: 'Forbidden',
        createdAt: '2026-01-01T00:00:00Z',
      },
    ],
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoIndexNowLog client={client} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('Lỗi (403)'))
  assert.ok(json.includes('Forbidden'))
})

test('truncates a long URL list rather than listing every one', async () => {
  const client = stubClient(async () => ({
    data: [
      {
        id: 1,
        urls: ['/a', '/b', '/c', '/d'],
        urlCount: 4,
        successful: true,
        statusCode: 200,
        error: null,
        createdAt: null,
      },
    ],
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoIndexNowLog client={client} />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('+2 nữa'))
})
