import assert from 'node:assert/strict'
import { test } from 'node:test'
import { act, create } from 'react-test-renderer'
import type { DashboardStats, SeoClient } from '@duxbo/seo-core'
import { SeoDashboard } from './SeoDashboard.js'

function stubClient(dashboard: () => Promise<DashboardStats>): SeoClient {
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
    dashboard,
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
  }
}

const baseStats: DashboardStats = {
  seoEnabled: true,
  totalRecords: 12,
  missingByType: { post: 3 },
  totalMissing: 3,
  activeRedirects: 2,
  notFoundCount: 1,
  sitemapSources: 1,
  exposedTypes: ['post'],
}

test('renders the fetched stats once loaded', async () => {
  const client = stubClient(async () => baseStats)
  let renderer: ReturnType<typeof create>

  await act(async () => {
    renderer = create(<SeoDashboard client={client} />)
  })

  const text = renderer!.toJSON() as unknown as string
  assert.ok(JSON.stringify(text).includes('12'))
  assert.ok(JSON.stringify(text).includes('post'))
})

test('shows the demo-domain warning only when seoEnabled is false', async () => {
  const client = stubClient(async () => ({ ...baseStats, seoEnabled: false }))
  let renderer: ReturnType<typeof create>

  await act(async () => {
    renderer = create(<SeoDashboard client={client} />)
  })

  assert.ok(JSON.stringify(renderer!.toJSON()).includes('SEO đang tắt toàn site'))
})

test('clicking a type calls onSelectType with that type', async () => {
  const client = stubClient(async () => baseStats)
  const selected: string[] = []
  let renderer: ReturnType<typeof create>

  await act(async () => {
    renderer = create(<SeoDashboard client={client} onSelectType={(t) => selected.push(t)} />)
  })

  const button = renderer!.root.findByProps({ children: 'Xem danh sách →' })

  act(() => {
    button.props.onClick()
  })

  assert.deepEqual(selected, ['post'])
})
