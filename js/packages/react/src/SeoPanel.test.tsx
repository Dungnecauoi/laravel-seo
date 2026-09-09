import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { ExternalSeoSignals, MetaResponse, SeoClient } from '@duxbo/seo-core'
import { SeoPanel } from './SeoPanel.js'

function stubClient(externalSignals: ExternalSeoSignals): SeoClient {
  const meta: MetaResponse = { stored: null, resolved: {}, locales: [], externalSignals }

  return {
    resolve: async () => ({ url: '/x', locale: null }),
    analyze: async () => ({ score: 0, locale: null, results: [] }),
    getMeta: async () => meta,
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
    brokenLinks: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
    aiTools: async () => ({ tools: [] }),
    aiToolCalls: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
  }
}

test('renders PageSpeed, Search Console, and broken-link signals once loaded', async () => {
  const client = stubClient({ pagespeedScore: 96, gscVerdict: 'PASS', brokenLinksCount: 0 })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoPanel client={client} target={{ type: 'post', id: 1 }} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('96/100'))
  assert.ok(json.includes('PASS'))
  assert.ok(json.includes('PageSpeed'))
  assert.ok(json.includes('Search Console'))
  assert.ok(json.includes('Link chết'))
})

test('a never-checked signal reads "Chưa kiểm tra", not a misleading zero', async () => {
  const client = stubClient({ pagespeedScore: null, gscVerdict: null, brokenLinksCount: null })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoPanel client={client} target={{ type: 'post', id: 1 }} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  const occurrences = json.split('Chưa kiểm tra').length - 1
  assert.equal(occurrences, 3)
})

test('a broken link count above zero renders distinctly from a clean page', async () => {
  const client = stubClient({ pagespeedScore: 42, gscVerdict: 'FAIL', brokenLinksCount: 3 })

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoPanel client={client} target={{ type: 'post', id: 1 }} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('"3"'))
  assert.ok(json.includes('42/100'))
  assert.ok(json.includes('FAIL'))
})
