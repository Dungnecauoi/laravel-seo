import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { BrokenLinksResponse, SeoClient } from '@duxbo/seo-core'
import { SeoBrokenLinks } from './SeoBrokenLinks.js'

function stubClient(brokenLinks: () => Promise<BrokenLinksResponse>): SeoClient {
  return {
    resolve: async () => ({ url: '/x', locale: null }),
    analyze: async () => ({ score: 0, locale: null, results: [] }),
    getMeta: async () => ({ stored: null, resolved: {}, locales: [], externalSignals: { pagespeedScore: null, gscVerdict: null, brokenLinksCount: null } }),
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
      brokenLinksCount: 0,
      notIndexedCount: 0,
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
    indexNowLog: async () => ({ data: [] }),
    googleIndexingLog: async () => ({ data: [] }),
    pageSpeedStats: async () => ({ strategy: 'mobile', data: [] }),
    urlInspections: async () => ({ data: [] }),
    brokenLinks,
    aiTools: async () => ({ tools: [] }),
    aiToolCalls: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
  }
}

test('shows the empty state with no broken links', async () => {
  const client = stubClient(async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoBrokenLinks client={client} />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('Không có link chết nào'))
})

test('renders a broken URL with its status and citing source count', async () => {
  const client = stubClient(async () => ({
    data: [
      {
        url: 'https://mot-trang-da-mat.com/x',
        statusCode: 404,
        error: 'HTTP 404',
        checkedAt: '2026-01-01T00:00:00Z',
        sources: [
          { sourceType: 'post', sourceId: '1', anchorText: 'Xem thêm' },
          { sourceType: 'post', sourceId: '2', anchorText: null },
        ],
      },
    ],
    meta: { currentPage: 1, lastPage: 1, total: 1 },
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoBrokenLinks client={client} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('HTTP 404'))
  assert.ok(json.includes('mot-trang-da-mat.com'))
  // The source count cell renders as a lone text node — a standalone quoted
  // "2" in the tree, not just the digit appearing anywhere in the JSON.
  assert.ok(json.includes('"2"'))
})

test('a connection error with no status code falls back to the error message', async () => {
  const client = stubClient(async () => ({
    data: [
      {
        url: 'https://khong-ket-noi-duoc.com/x',
        statusCode: null,
        error: 'Connection timed out',
        checkedAt: null,
        sources: [],
      },
    ],
    meta: { currentPage: 1, lastPage: 1, total: 1 },
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoBrokenLinks client={client} />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('Connection timed out'))
})
