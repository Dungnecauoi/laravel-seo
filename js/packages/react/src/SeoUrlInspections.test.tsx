import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { SeoClient, UrlInspectionsResponse } from '@duxbo/seo-core'
import { SeoUrlInspections } from './SeoUrlInspections.js'

function stubClient(urlInspections: () => Promise<UrlInspectionsResponse>): SeoClient {
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
    indexNowLog: async () => ({ data: [] }),
    googleIndexingLog: async () => ({ data: [] }),
    pageSpeedStats: async () => ({ strategy: 'mobile', data: [] }),
    urlInspections,
    brokenLinks: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
    aiTools: async () => ({ tools: [] }),
    aiToolCalls: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
  }
}

test('shows the empty state with no inspections', async () => {
  const client = stubClient(async () => ({ data: [] }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoUrlInspections client={client} />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('Chưa có dữ liệu'))
})

test('renders a passing verdict distinctly from a failing one', async () => {
  const client = stubClient(async () => ({
    data: [
      {
        url: 'https://vidu.vn/tot',
        verdict: 'PASS',
        coverageState: 'Submitted and indexed',
        robotsTxtState: 'ALLOWED',
        indexingState: 'INDEXING_ALLOWED',
        pageFetchState: 'SUCCESSFUL',
        googleCanonical: 'https://vidu.vn/tot',
        userCanonical: 'https://vidu.vn/tot',
        mobileUsabilityVerdict: 'PASS',
        mobileUsabilityIssues: [],
        richResultsVerdict: 'NEUTRAL',
        date: '2026-01-01',
      },
      {
        url: 'https://vidu.vn/xau',
        verdict: 'FAIL',
        coverageState: 'Crawled - currently not indexed',
        robotsTxtState: 'ALLOWED',
        indexingState: 'INDEXING_ALLOWED',
        pageFetchState: 'SUCCESSFUL',
        googleCanonical: null,
        userCanonical: null,
        mobileUsabilityVerdict: 'FAIL',
        mobileUsabilityIssues: ['TEXT_TOO_SMALL'],
        richResultsVerdict: null,
        date: '2026-01-01',
      },
    ],
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoUrlInspections client={client} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('PASS'))
  assert.ok(json.includes('FAIL'))
  assert.ok(json.includes('Submitted and indexed'))
  assert.ok(json.includes('Crawled - currently not indexed'))
})
