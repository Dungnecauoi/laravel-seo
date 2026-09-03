import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { SeoClient, SettingsResponse } from '@duxbo/seo-core'
import { SeoSettings } from './SeoSettings.js'

function stubClient(settings: () => Promise<SettingsResponse>): SeoClient {
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
    settings,
    redirects: async () => ({ data: [], meta: { currentPage: 1, lastPage: 1, total: 0 } }),
    createRedirect: async () => ({ id: 1 }),
    toggleRedirect: async () => ({ isActive: true }),
    deleteRedirect: async () => {},
  }
}

const base: SettingsResponse = {
  seoEnabled: true,
  indexableEnvironments: ['production'],
  currentEnvironment: 'production',
  apiEnabled: true,
  panelEnabled: true,
  exposedModels: ['post'],
  allowedHosts: [],
  sitemapSourceCount: 2,
  aiDriver: 'claude',
  aiBudget: 100000,
  analysisRateLimit: '30,1',
  supportedLocales: ['vi', 'en'],
}

test('a disabled master switch renders the demo-domain warning pill', async () => {
  const client = stubClient(async () => ({ ...base, seoEnabled: false }))

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoSettings client={client} />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('Tắt — noindex toàn site'))
})

test('an enabled master switch renders the ok pill, not the warning', async () => {
  const client = stubClient(async () => base)

  let renderer: ReturnType<typeof create>
  await act(async () => {
    renderer = create(<SeoSettings client={client} />)
  })

  const json = JSON.stringify(renderer!.toJSON())
  assert.ok(json.includes('claude'))
  assert.ok(!json.includes('Tắt — noindex toàn site'))
})
