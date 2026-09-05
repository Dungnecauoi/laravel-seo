import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { SearchConsoleStatsResponse, SeoClient } from '@duxbo/seo-core'
import { SeoSearchConsoleStats } from './SeoSearchConsoleStats.js'

function stubClient(searchConsoleStats: (days?: number) => Promise<SearchConsoleStatsResponse>): SeoClient {
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
    searchConsoleStats,
    indexNowLog: async () => ({ data: [] }),
  }
}

test('renders rows summed per URL', async () => {
  const client = stubClient(async () => ({
    days: 30,
    totalClicks: 17,
    totalImpressions: 150,
    data: [{ url: 'https://trangcuatoi.vn/a', clicks: 17, impressions: 150, ctr: 0.1133, position: 4.2 }],
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoSearchConsoleStats client={client} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('trangcuatoi.vn/a'))
  assert.ok(json.includes('17'))
  assert.ok(json.includes('11.3%'))
})

test('switching the window re-fetches with the new day count', async () => {
  let lastDays: number | undefined
  const client = stubClient(async (days) => {
    lastDays = days
    return { days: days ?? 30, totalClicks: 0, totalImpressions: 0, data: [] }
  })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoSearchConsoleStats client={client} />)
  })

  const button = renderer!.root.findByProps({ children: '7 ngày' })

  await act(async () => {
    button.props.onClick()
  })

  assert.equal(lastDays, 7)
})
