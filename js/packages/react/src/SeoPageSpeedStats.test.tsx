import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { PageSpeedStatsResponse, SeoClient } from '@duxbo/seo-core'
import { SeoPageSpeedStats } from './SeoPageSpeedStats.js'

function stubClient(pageSpeedStats: (strategy?: 'mobile' | 'desktop') => Promise<PageSpeedStatsResponse>): SeoClient {
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
    pageSpeedStats,
    urlInspections: async () => ({ data: [] }),
    brokenLinks: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
    aiTools: async () => ({ tools: [] }),
    aiToolCalls: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
  }
}

test('shows the empty state with no stats', async () => {
  const client = stubClient(async () => ({ strategy: 'mobile', data: [] }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoPageSpeedStats client={client} />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('Chưa có dữ liệu'))
})

test('colors a low score distinctly from a good one', async () => {
  const client = stubClient(async () => ({
    strategy: 'mobile',
    data: [
      {
        url: 'https://vidu.vn/cham',
        strategy: 'mobile',
        performanceScore: 32,
        lcpMs: 4200,
        clsScore: 0.25,
        tbtMs: 800,
        fcpMs: 3000,
        speedIndexMs: 5000,
        fieldDataAvailable: true,
        cwvCategory: 'SLOW',
        date: '2026-01-01',
      },
    ],
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoPageSpeedStats client={client} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('32/100'))
  assert.ok(json.includes('SLOW'))
  assert.ok(json.includes('4.2s'))
})

test('a URL with no field data yet shows that distinctly from a real category', async () => {
  const client = stubClient(async () => ({
    strategy: 'mobile',
    data: [
      {
        url: 'https://vidu.vn/moi',
        strategy: 'mobile',
        performanceScore: 95,
        lcpMs: 1200,
        clsScore: 0.01,
        tbtMs: 50,
        fcpMs: 700,
        speedIndexMs: 1000,
        fieldDataAvailable: false,
        cwvCategory: null,
        date: '2026-01-01',
      },
    ],
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoPageSpeedStats client={client} />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('Chưa đủ dữ liệu'))
})

test('switching the strategy re-fetches with the new value', async () => {
  const seen: (string | undefined)[] = []
  const client = stubClient(async (strategy) => {
    seen.push(strategy)

    return { strategy: strategy ?? 'mobile', data: [] }
  })

  await act(async () => {
    create(<SeoPageSpeedStats client={client} strategy="desktop" />)
  })

  assert.deepEqual(seen, ['desktop'])
})
