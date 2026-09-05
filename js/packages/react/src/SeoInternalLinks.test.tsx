import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { InternalLinksResponse, SeoClient } from '@duxbo/seo-core'
import { SeoInternalLinks } from './SeoInternalLinks.js'

function stubClient(internalLinks: (type?: string) => Promise<InternalLinksResponse>): SeoClient {
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
    internalLinks,
    searchConsoleStats: async () => ({ days: 30, totalClicks: 0, totalImpressions: 0, data: [] }),
    indexNowLog: async () => ({ data: [] }),
  }
}

test('flags a row with zero incoming links as an orphan', async () => {
  const client = stubClient(async () => ({
    exposedTypes: ['post'],
    type: 'post',
    data: [
      { id: 1, url: '/bai-a', incomingLinks: 2, outgoingLinks: 1, isOrphan: false },
      { id: 2, url: '/bai-b', incomingLinks: 0, outgoingLinks: 3, isOrphan: true },
    ],
    meta: { currentPage: 1, lastPage: 1, total: 2 },
  }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoInternalLinks client={client} type="post" />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('Mồ côi'))
  assert.ok(json.includes('/bai-a'))
})

test('shows the empty state with no rows', async () => {
  const client = stubClient(async () => ({ exposedTypes: ['post'], type: 'post', data: [], meta: null }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoInternalLinks client={client} type="post" />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('Không có bản ghi nào'))
})
